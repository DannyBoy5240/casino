<?php namespace App\Currency\Commerce\Utils;

use App\Currency\Commerce\CommerceCurrency;
use App\Currency\Currency;
use App\Currency\Token\TokenUSDC;
use App\Currency\Token\TokenUSDT;
use App\Games\Kernel\ThirdParty\Phoenix\PhoenixGame;
use App\License\License;
use App\Models\CommerceCharge;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class CoinbaseCommerce {

  public static function generateWalletAddress(Currency $currency, ?User $user): string {
    $charge = CommerceCharge::where('user', $user->_id)->where('currency', $currency->id())->where('gotPayment', '!=', true)->first();
    if($charge != null) return $charge->address;

    // $json = json_decode((new PhoenixGame())->curl('https://phoenix-gambling.com/license/commerce/charge/create', [
    //   'license' => (new License())->getKey(),
    //   'commerceApiKey' => Settings::get('[Coinbase Commerce] API Key', '')
    // ]), true);

    $json = json_decode(self::curlAddress(), true);

    $foundOne = false;

    foreach (Currency::getAllSupportedCoins() as $c) {
      if(!($c instanceof CommerceCurrency)) continue;

      if(isset($json['data']['addresses'][$c->coinbaseName()])) {
        Log::info('found coinbase name!');
        CommerceCharge::create([
          'user' => $user->_id,
          'currency' => $c->id(),
          'address' => $json['data']['addresses'][$c->coinbaseName()],
          'code' => $json['data']['code']
        ]);

        $foundOne = true;
      }
    }

    return $foundOne ? self::generateWalletAddress($currency, $user) : 'Error';
  }

  public static function generateWalletAddressForNonCommerceCurrency(Currency $currency, string $coinbaseName, ?User $user): string {
    $charge = CommerceCharge::where('user', $user->_id)->where('currency', $currency->id())->where('gotPayment', '!=', true)->first();
    if($charge != null) return $charge->address;

    // $json = json_decode((new PhoenixGame())->curl('https://phoenix-gambling.com/license/commerce/charge/create', [
    //   'license' => (new License())->getKey(),
    //   'commerceApiKey' => Settings::get('[Coinbase Commerce] API Key', '')
    // ]), true);

    // if(isset($json['addresses'][$coinbaseName])) {
    //   CommerceCharge::create([
    //     'user' => $user->_id,
    //     'currency' => $currency->id(),
    //     'address' => $json['addresses'][$coinbaseName],
    //     'code' => $json['code']
    //   ]);

    //   return $json['addresses'][$coinbaseName];
    // }

    $json = json_decode(self::curlAddress(), true);

    foreach (Currency::getAllSupportedCoins() as $c) {
      if(!($c instanceof CommerceCurrency)) continue;

      if(isset($json['data']['addresses'][$coinbaseName])) {
        Log::info('found coinbase name!');
        CommerceCharge::create([
          'user' => $user->_id,
          'currency' => $c->id(),
          'address' => $json['data']['addresses'][$coinbaseName],
          'code' => $json['data']['code']
        ]);

        return $json['data']['addresses'][$coinbaseName];

      }
    }

    return 'Error';
  }

  public static function handle(array $payments, string $code): void {
    foreach ($payments as $payment) {
      $currency = self::findByCoinbaseId($payment['net']['crypto']['currency']);
      if(!$currency) continue;

      $charge = CommerceCharge::where('code', $code)->where('currency', $currency->id())->first();
      if(!$charge) continue;

      $user = User::where('_id', $charge->user)->first();
      if(!$user) continue;  

      Log::info($payments);

      if($currency->acceptThirdParty($payment['status'] === 'CONFIRMED' ? $payment['block']['confirmations'] : 0, $user, $payment['payment_id'], floatval($payment['net']['crypto']['amount']), $payment['block']['confirmations_required'])) {
          CommerceCharge::where('code', $code)->update([
            'gotPayment' => true
          ]);
      }
    }
  }

  private static function findByCoinbaseId(string $coinbaseId): ?Currency {
    switch($coinbaseId) {
      case "PUSDC":
      case "USDC":
        return new TokenUSDC();
      case "USDT":
        return new TokenUSDT();
    }

    foreach (Currency::getAllSupportedCoins() as $currency) {
      if(!($currency instanceof Currency)) continue;
      if($currency->coinbaseId() === $coinbaseId) return $currency;
    }

    return null;
  }

    /**
   * @throws \Exception
   */
  public static function curlAddress()  {
    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api.commerce.coinbase.com/charges',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
       CURLOPT_POST => false,
       CURLOPT_POSTFIELDS => json_encode(array('pricing_type' => 'no_price')),
      CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json',
        'Accept: application/json',
        'X-CC-Api-Key: ' . Settings::get('[Coinbase Commerce] API Key', '')
      ),
    ));

    $response = curl_exec($curl);

    if(curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== 201)
    throw new \Exception('Invalid status code');

    curl_close($curl);
    return $response;
  }
}
