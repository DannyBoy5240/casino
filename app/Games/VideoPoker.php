<?php namespace App\Games;

use App\Models\Game;
use App\Games\Kernel\Data;
use App\Games\Kernel\Extended\ContinueGame;
use App\Games\Kernel\Extended\ExtendedGame;
use App\Games\Kernel\Extended\FinishGame;
use App\Games\Kernel\Extended\Turn;
use App\Games\Kernel\GameCategory;
use App\Games\Kernel\Metadata;
use App\Games\Kernel\Module\General\HouseEdgeModule;
use App\Games\Kernel\Module\General\Wrapper\MultiplierCanBeLimited;
use App\Games\Kernel\ProvablyFair;
use App\Games\Kernel\ProvablyFairResult;
use Illuminate\Support\Facades\Log;

class VideoPoker extends ExtendedGame implements MultiplierCanBeLimited {

  function metadata(): Metadata {
    return new class extends Metadata {
      function id(): string {
        return 'videopoker';
      }

      function name(): string {
        return 'VideoPoker';
      }

      function icon(): string {
        return 'fas fa-spade';
      }

      public function category(): array {
        return [GameCategory::$originals, GameCategory::$table];
      }
    };
  }

  public function start(\App\Models\Game $game) {}

  public function turn(\App\Models\Game $game, array $turnData): Turn {
    if ($this->getTurn($game) === 1) {
      // $game->update([
      //   'multiplier' => 1
      // ]);
    //  Log::info('deck generated in turn: ');
    //  Log::info(array_slice((new ProvablyFair($this, $game->server_seed))->result()->result(), 5, 10));
      $this->pushData($game, ['deck' => array_slice((new ProvablyFair($this, $game->server_seed, $game->id))->result()->result(), 5, 10)]);
      $game->update([
        'multiplier' => $this->getMultiplier($game, $turnData, true, $game->server_seed)
      ]);
      $this->pushHistory($game, ['deck' => $this->gameData($game)['deck']]);

      return new ContinueGame($game, ['deck' => $this->gameData($game)['deck']]);
    } else {
      $game->update([
        'multiplier' => $this->getMultiplier($game, $turnData, true, $game->server_seed)
      ]);
      $this->pushHistory($game, ['deck' => $this->gameData($game)['deck']]);
      return new FinishGame($game, ['deck' => $this->gameData($game)['deck']]);
    }
  }

  private function getMultiplier(Game $game, ?array $turnData, bool $replace, string $server_seed) {
    $deck = (new ProvablyFair($this, $server_seed, $game->id))->result()->result();
   // Log::info('deck generated in getMutliplier: ');
   // Log::info($deck);

    if ($turnData != null) {
      if ($replace) {
        if (count($turnData['hold']) > 5) $turnData['hold'] = [];

        $deck = [
          in_array(0, $turnData['hold']) ? $deck[5] : $deck[0],
          in_array(1, $turnData['hold']) ? $deck[6] : $deck[1],
          in_array(2, $turnData['hold']) ? $deck[7] : $deck[2],
          in_array(3, $turnData['hold']) ? $deck[8] : $deck[3],
          in_array(4, $turnData['hold']) ? $deck[9] : $deck[4],
        ];
      }

      $this->pushData($game, ['deck' => $deck]);
    }
    else $deck = array_slice((new ProvablyFair($this, $game->server_seed, $game->id))->result()->result(), 5, 10);

    $userValues = [];
    $userTypes = [];
    $userSlots = [];
    $userSlotsStraight = [];
    $isStraight = true;

    for ($i = 0; $i < 5; $i++) {
      $userValues[] = $this->deck()[$deck[$i] + 1]['value'];
      $userTypes[] = $this->deck()[$deck[$i] + 1]['type'];
      $userSlots[] = $this->deck()[$deck[$i] + 1]['slot'];
      // if(is_numeric($this->deck()[$deck[$i] + 1]['value']))
      //   $userSlotsStraight[] = $this->deck()[$deck[$i] + 1]['slot'];
    }
    //Log::info('user value array is:' );
   // Log::info($userValues);
    $tmpSlots = array_merge($userSlots); // creating a deep copy
    sort($tmpSlots);
    for($i = 1; $i < 5; $i++) {
      if(abs($tmpSlots[$i] - $tmpSlots[$i-1]) > 1) $isStraight = false;
    }
    $isStraight |= (in_array('A', $userValues) && in_array('K', $userValues) && in_array('Q', $userValues) && in_array('J', $userValues) && in_array('10', $userValues) || in_array('A', $userValues) && in_array('2', $userValues) && in_array('3', $userValues) && in_array('4', $userValues) && in_array('5', $userValues));

   // $checkForStraight = count($userSlotsStraight) == 5 && count(array_unique($userSlotsStraight)) == count($userSlotsStraight);
   // $totalSlots = $userSlots[0] + $userSlots[1] + $userSlots[2] + $userSlots[3] + $userSlots[4];
   // $isStraight = $checkForStraight && ($totalSlots == 20 || $totalSlots == 25 || $totalSlots == 30 || $totalSlots == 35 || $totalSlots == 40);


    $isFlush = (count(array_count_values($userTypes)) == 1);

    $o = array_count_values($userValues);
    $pairs = 0;
    $triplets = 0;
    $fours = 0;
    $validPair = false;

    foreach ($o as $value => $occur) {
      //Log::info($value);
      //Log::info($occur);
      $isJQKA = ($value == 'J' || $value == 'Q' || $value == 'K' || $value == 'A');

      if ($occur >= 4) $fours++;
      else if ($occur == 3) $triplets++;
      else if ($occur == 2 ) $pairs++;

      if($isJQKA && $occur == 2) $validPair = true;
    }


    if (in_array('A', $userValues) && in_array('K', $userValues) && in_array('Q', $userValues) && in_array('J', $userValues) && in_array('10', $userValues) && $isFlush) return HouseEdgeModule::apply($this, 800);
    else if ($isStraight && $isFlush) return HouseEdgeModule::apply($this, 60);
    else if ($fours == 1) return HouseEdgeModule::apply($this, 22);
    else if ($triplets == 1 && $pairs == 1) return HouseEdgeModule::apply($this, 9);
    else if ($isFlush) return HouseEdgeModule::apply($this, 6);
    else if ($isStraight) return HouseEdgeModule::apply($this, 4);
    else if ($triplets == 1) return HouseEdgeModule::apply($this, 3);
    else if ($pairs == 2) return HouseEdgeModule::apply($this, 2);
    else if ($pairs == 1 && $validPair) return HouseEdgeModule::apply($this, 1);
    return 0;
  }

  public function isLoss(ProvablyFairResult $result, \App\Models\Game $game, array $turnData): bool {
    return $this->getMultiplier($game, $turnData, $this->getTurn($game) == 1, $result->server_seed()) < 1;
  }

  function result(ProvablyFairResult $result): array {
    return $this->getCards($result, 10, true);
  }

  private function deck() {
    return [
      1 => ['type' => 'spades', 'value' => 'A', 'slot' => 0],
      2 => ['type' => 'spades', 'value' => '2', 'slot' => 1],
      3 => ['type' => 'spades', 'value' => '3', 'slot' => 2],
      4 => ['type' => 'spades', 'value' => '4', 'slot' => 3],
      5 => ['type' => 'spades', 'value' => '5', 'slot' => 4],
      6 => ['type' => 'spades', 'value' => '6', 'slot' => 5],
      7 => ['type' => 'spades', 'value' => '7', 'slot' => 6],
      8 => ['type' => 'spades', 'value' => '8', 'slot' => 7],
      9 => ['type' => 'spades', 'value' => '9', 'slot' => 8],
      10 => ['type' => 'spades', 'value' => '10', 'slot' => 9],
      11 => ['type' => 'spades', 'value' => 'J', 'slot' => 10],
      12 => ['type' => 'spades', 'value' => 'Q', 'slot' => 11],
      13 => ['type' => 'spades', 'value' => 'K', 'slot' => 12],
      14 => ['type' => 'hearts', 'value' => 'A', 'slot' => 0],
      15 => ['type' => 'hearts', 'value' => '2', 'slot' => 1],
      16 => ['type' => 'hearts', 'value' => '3', 'slot' => 2],
      17 => ['type' => 'hearts', 'value' => '4', 'slot' => 3],
      18 => ['type' => 'hearts', 'value' => '5', 'slot' => 4],
      19 => ['type' => 'hearts', 'value' => '6', 'slot' => 5],
      20 => ['type' => 'hearts', 'value' => '7', 'slot' => 6],
      21 => ['type' => 'hearts', 'value' => '8', 'slot' => 7],
      22 => ['type' => 'hearts', 'value' => '9', 'slot' => 8],
      23 => ['type' => 'hearts', 'value' => '10', 'slot' => 9],
      24 => ['type' => 'hearts', 'value' => 'J', 'slot' => 10],
      25 => ['type' => 'hearts', 'value' => 'Q', 'slot' => 11],
      26 => ['type' => 'hearts', 'value' => 'K', 'slot' => 12],
      27 => ['type' => 'clubs', 'value' => 'A', 'slot' => 0],
      28 => ['type' => 'clubs', 'value' => '2', 'slot' => 1],
      29 => ['type' => 'clubs', 'value' => '3', 'slot' => 2],
      30 => ['type' => 'clubs', 'value' => '4', 'slot' => 3],
      31 => ['type' => 'clubs', 'value' => '5', 'slot' => 4],
      32 => ['type' => 'clubs', 'value' => '6', 'slot' => 5],
      33 => ['type' => 'clubs', 'value' => '7', 'slot' => 6],
      34 => ['type' => 'clubs', 'value' => '8', 'slot' => 7],
      35 => ['type' => 'clubs', 'value' => '9', 'slot' => 8],
      36 => ['type' => 'clubs', 'value' => '10', 'slot' => 9],
      37 => ['type' => 'clubs', 'value' => 'J', 'slot' => 10],
      38 => ['type' => 'clubs', 'value' => 'Q', 'slot' => 11],
      39 => ['type' => 'clubs', 'value' => 'K', 'slot' => 12],
      40 => ['type' => 'diamonds', 'value' => 'A', 'slot' => 0],
      41 => ['type' => 'diamonds', 'value' => '2', 'slot' => 1],
      42 => ['type' => 'diamonds', 'value' => '3', 'slot' => 2],
      43 => ['type' => 'diamonds', 'value' => '4', 'slot' => 3],
      44 => ['type' => 'diamonds', 'value' => '5', 'slot' => 4],
      45 => ['type' => 'diamonds', 'value' => '6', 'slot' => 5],
      46 => ['type' => 'diamonds', 'value' => '7', 'slot' => 6],
      47 => ['type' => 'diamonds', 'value' => '8', 'slot' => 7],
      48 => ['type' => 'diamonds', 'value' => '9', 'slot' => 8],
      49 => ['type' => 'diamonds', 'value' => '10', 'slot' => 9],
      50 => ['type' => 'diamonds', 'value' => 'J', 'slot' => 10],
      51 => ['type' => 'diamonds', 'value' => 'Q', 'slot' => 11],
      52 => ['type' => 'diamonds', 'value' => 'K', 'slot' => 12]
    ];
  }

  public function multiplier(?Game $game, ?Data $data, ProvablyFairResult $result): float {
    return $this->getMultiplier($game, null, false, $result->server_seed());
  }

  public function getBotTurnData($turnId): array {
    return [
      'hold' => [-1, -1, -1, -1, -1, -1]
    ];
  }

}
