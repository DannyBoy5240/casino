<?php namespace App\Games;

use App\Currency\Currency;
use App\Events\MultiplayerTimerStart;
use App\Games\Kernel\Data;
use App\Games\Kernel\GameCategory;
use App\Games\Kernel\Metadata;
use App\Games\Kernel\Module\General\HouseEdgeModule;
use App\Games\Kernel\Multiplayer\MultiplayerGame;
use App\Games\Kernel\ProvablyFair;
use App\Games\Kernel\ProvablyFairResult;
use App\Games\Kernel\Game;
use App\Jobs\MultiplayerDisableBetAccepting;
use App\Jobs\MultiplayerFinishAndSetupNextGame;
use App\Jobs\MultiplayerUpdateTimestamp;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Settings;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use App\Jobs\MultiplayerCrashUpdateData;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Redis;
use Faker\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Events\MultiplayerGameBet;

class Crash extends MultiplayerGame {

    function metadata(): Metadata {
        return new class extends Metadata {
            function id(): string {
                return 'crash';
            }

            function name(): string {
                return 'Crash';
            }

            function icon(): string {
                return 'crash';
            }
            
            function multiplayer(): int {
                return 1;
            }           

            public function category(): array {
                return [GameCategory::$originals];
            }
        };
    }

    public function startChain() {
        dispatch(new MultiplayerFinishAndSetupNextGame($this, ['multiplier' => 1], now()->addSeconds(6)));
    }

    public function nextGame() {
        $this->state()->resetPlayers();
        $this->state()->clientSeed(ProvablyFair::generateServerSeed());
        $this->state()->serverSeed(ProvablyFair::generateServerSeed());
        $this->state()->nonce(now()->timestamp);
        $this->state()->timestamp(now()->timestamp);

        $multiplier = (new ProvablyFair($this))->result()->result()[0];
        $multiplier = $multiplier > 40 ? floatval(rand(30, 40).'.'.rand(0, 99)) : $multiplier;
        if($multiplier < 1) $multiplier = 1.1;

        // $timeInMilliseconds = 0;
        // $simulation = 1; $suS =  0;

        // What's the point of this simulation??
        // while($simulation < $multiplier) {
        //     $simulation += 0.05 / 15 + $suS;
        //     $timeInMilliseconds += 2000 / 15 / 3;
        //     if($simulation >= 5.5) {
        //         $suS += 0.05 / 15;
        //         $timeInMilliseconds += 4000 / 15 / 3;
        //     }
        // }

        $this->state()->betting(true);
        $this->state()->timestamp(now()->addSeconds(6)->timestamp);


        Redis::del('crash');

        dispatch((new MultiplayerDisableBetAccepting($this))->delay(now()->addSeconds(6))); 
        
        dispatch((new MultiplayerCrashUpdateData($this, $multiplier))->delay(now()->addSeconds(6)));

        // MultiplayerDisableBetAccepting::dispatch($this)->delay(now()->addSeconds(6));
        // MultiplayerCrashUpdateData::dispatch($this, $multiplier)->delay(now()->addSeconds(6));


        // \Illuminate\Support\Facades\Bus::chain([
        //     (new MultiplayerDisableBetAccepting($this)),
        //     (new MultiplayerCrashUpdateData($this, $multiplier)),
        // ])->dispatch()->delay(now()->addSeconds(6));

       // dispatch((new MultiplayerDisableBetAccepting($this))->delay(now()->addSeconds(6)))

        // MultiplayerDisableBetAccepting::withChain([
        //     (new MultiplayerCrashUpdateData($this, $multiplier))
        // ])->dispatch($this)->delay(now()->addSeconds(6));

        // Bus::chain([
        //     new ProcessPodcast,
        //     new OptimizePodcast,
        //     new ReleasePodcast,
        // ])->onConnection('redis')->onQueue('podcasts')->dispatch();

        $t1 = microtime(true);

        event(new MultiplayerTimerStart($this));

        if(Settings::get('[Crash Bot] Stop', 'true', true) === 'false') {
            if(!Cache::get('crash_bots_created', false) || (count(Cache::get('crash_bots')) < Settings::get('amount_of_crash_bots'))) {

            //    Log::info('pravim botove');
                $createUsername  = function() use(&$createUsername) {
                    $faker = Factory::create();
                    $username = $faker->userName;
                    if(User::where('name', $username)->first() != null) return $createUsername();
                    return str_replace('.', mt_rand(0, 2) === 1 ? '_' : '', $username);
                };

                $bots = [];

                for($i = 0; $i < Settings::get('amount_of_crash_bots'); $i++) {
                    $username = $createUsername();
                    $user = User::create([
                        'name' => $username,
                        'password' => Hash::make(uniqid()),
                        'avatar' => '/avatar/' . uniqid(),
                        'email' => null,
                        'client_seed' => \App\Games\Kernel\ProvablyFair::generateServerSeed(),
                        'roles' => [],
                        'name_history' => [['time' => \Carbon\Carbon::now(), 'name' => $username]],
                        'register_ip' => null,
                        'login_ip' => null,
                        'bot' => true,
                        'register_multiaccount_hash' => null,
                        'login_multiaccount_hash' => null,
                        'private_bets' => mt_rand(0, 100) <= floatval(Settings::get('hidden_bets_probability_crash', 20, true)),
                        'private_profile' => mt_rand(0, 100) <= floatval(Settings::get('hidden_profile_probability_crash', 20, true))
                    ]);
                //   Log::info('Bot user generated');
              //      Log::info($user->toArray());
                    array_push($bots, $user);
                }
                Cache::set('crash_bots', $bots);
                Cache::set('crash_bots_created', true, now()->addMinutes(30));
             //   Log::info('botovi su napravljeni i spremljeni');
          //      Log::info(Cache::get('crash_bots'));
            }

            $bots = Cache::get('crash_bots');
            // Log::info('botovi su napravljeni');
            // Log::info($bots);

            shuffle($bots);
            $rnd_number_of_bots = mt_rand(1, Settings::get('amount_of_crash_bots'));

            for($i = 0; $i < $rnd_number_of_bots; $i++) {                         
                    $currencies = Currency::all();
                    $currency = $currencies[mt_rand(0, count($currencies) - 1)];
                   // Log::info('valuta beta je: ' . $currency->id());
                    $bet = $currency->getBotBet();
                    $diff = 1000000;
                    $multiplier = min(mt_rand(1 * $diff, 30 * $diff) / $diff, mt_rand(1*$diff, 30*$diff) / $diff) + 0.01;

                    //  $data = new Data($this->user, [
                    //     'api_id' => $data->api_id,
                    //     'bet' => $bet,
                    //     'currency' => $currency->id(),
                    //     'demo' => false,
                    //     'quick' => false,
                    //     'data' => (array) $data->data,
                    //     'autoCashoutMultiplier' => $multiplier
                    // ]);
                    
                    $game = \App\Models\Game::create([
                        'id' => DB::table('games')->count() + 1,
                        'user' => $bots[$i]->_id,
                        'game' => $this->metadata()->id(),
                        'wager' => $bet,
                        'status' => 'in-progress',
                        'profit' => 0,
                        'server_seed' => $this->server_seed(),
                        'client_seed' => $this->client_seed(),
                        'nonce' => $this->nonce(),
                        'multiplier' => 0,
                        'currency' => $currency->id(),
                        'data' => [
                            'turn' => 0,
                            'history' => [],
                            'game_data' => [],
                            'user_data' => null
                        ],
                        'demo' => false,
                        'type' => $this instanceof MultiplayerGame ? 'multiplayer' : 'extended',
                        'bet_usd_converted' => Currency::find($currency->id())->convertTokenToFiat($bet)
                    ]);

                    // Log::info('bot game napravljen');
                    // Log::info($game->toArray());


                    Redis::zrem('crash', $game->_id);
//                    Log::info('bot autocashout multiplier je: ' . $multiplier * 100);
  //                  Log::info('bot autocashout multiplier je: ' . round($multiplier * 100, 0));
                    Redis::zadd('crash', round($multiplier * 100, 0), $game->_id);

                    $this->state()->players([
                        'user' => $bots[$i]->toArray(),
                        'game' => $game->toArray(),
                        'data' => $this->getPlayerData($game)
                    ]);

                    $data = $this->getPlayerData($game);
                    event(new MultiplayerGameBet($bots[$i], $game, $data));
                
            }
        }

        $t2 = microtime(true);

       // usleep(6000 - ($t2 - $t1));
    }

    public function onDispatchedFinish() {
        $this->state()->history([
            'server_seed' => $this->server_seed(),
            'client_seed' => $this->client_seed(),
            'nonce' => $this->nonce(),
            'multiplier' => $this->getCurrentMultiplier()
        ]);

        foreach($this->getActiveGames() as $game) {
            $game->update(['status' => 'lose']);
            event(new \App\Events\LiveFeedGame($game, 0));
            \App\Games\Kernel\Game::analytics($game);
        }
    }

    protected function allowCancellation(): bool {
        return true;
    }

    protected function canBeFinished(): bool {
        return true;
    }

    protected function handleCancellation(\App\Models\Game $game) {
        // check is the server seed of game object from db same as server seed of currently running game
        if($game->server_seed !== $this->state()->serverSeed()) return ['error' => [1, 'This Crash game is invalid']]; 

        $multiplier = $this->getCurrentMultiplier(); // fetch current multiplier which may not necessary be the same as one when player clicked!
        if($multiplier > 1000) $multiplier = 1;

        $game->update([
            'status' => 'win',
            'profit' => $game->wager * $multiplier,
            'multiplier' => $multiplier
        ]);
        User::where('_id', $game->user)->first()->balance(Currency::find($game->currency))->demo($game->demo)
            ->add($game->profit, Transaction::builder()->message('Crash (Take)')->game($this->metadata()->id())->get());
        event(new \App\Events\LiveFeedGame($game, 0));
        \App\Games\Kernel\Game::analytics($game);
        event(new \App\Events\MultiplayerBetCancellation($game, User::where('_id', $game->user)->first()));
    }

    private function getCurrentMultiplier() {
        $start_timestamp = $this->state()->timestamp();
        if($start_timestamp < 0) return 1;

        $diffS = now()->timestamp - $start_timestamp;
        $timeInMilliseconds = 0;
        $simulation = 1; $suS =  0;

        while(true) {
            $simulation += 0.05 / 15 + $suS;
            $timeInMilliseconds += 2000 / 15 / 3;
            if($simulation >= 5.5) {
                $suS += 0.05 / 15;
                $timeInMilliseconds += 4000 / 15 / 3;
            }
            if($timeInMilliseconds >= ($diffS * 1000) || $simulation > 1000) {
                if($simulation > 1000) $simulation = 1;
                break;
            }
        }

       // return HouseEdgeModule::apply($this, $simulation);
        //return HouseEdgeModule::apply($this, $simulation);
        return max($simulation, 1.01);
    }

    public function isLoss(ProvablyFairResult $result, \App\Models\Game $game, array $turnData): bool {
        return $result->result()[0] <= 1.1;
    }

    function result(ProvablyFairResult $result): array {
        $max_multiplier = 1000; $house_edge = HouseEdgeModule::get($this, 0.99);
        $float_point = $max_multiplier / ($result->extractFloat() * $max_multiplier) * $house_edge;
        return [floor($float_point * 100) / 100];
    }

}