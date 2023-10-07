<?php namespace App\Games;

use App\Models\Game;
use App\Games\Kernel\Data;
use App\Games\Kernel\Extended\ContinueGame;
use App\Games\Kernel\Extended\ExtendedGame;
use App\Games\Kernel\Extended\FailedTurn;
use App\Games\Kernel\Extended\FinishGame;
use App\Games\Kernel\Extended\LoseGame;
use App\Games\Kernel\Extended\Turn;
use App\Games\Kernel\GameCategory;
use App\Games\Kernel\Metadata;
use App\Games\Kernel\Module\General\Wrapper\MultiplierCanBeLimited;
use App\Games\Kernel\ProvablyFair;
use App\Games\Kernel\ProvablyFairResult;
use Illuminate\Support\Facades\Cache;
use App\Games\Kernel\Module\General\HouseEdgeModule;


class Tower extends ExtendedGame implements MultiplierCanBeLimited {

  function metadata(): Metadata {
    return new class extends Metadata {
      function id(): string {
        return 'tower';
      }

      function name(): string {
        return 'Tower';
      }

      function icon(): string {
        return 'fad fa-gopuram';
      }

      public function category(): array {
        return [GameCategory::$originals];
      }

      public function image(): string {
        return "/img/game/tower.png";
      }
    };
  }

  public function start(\App\Models\Game $game) {
    $this->pushData($game, [
      'mines' => intval($this->userData($game)['data']['mines'])
    ]);
  }

  public function getModuleData(\App\Models\Game $game) {
    return floatval($this->gameData($game)['mines']);
  }

  public function turn(\App\Models\Game $game, array $turnData): Turn {
    if (intval($turnData['cell']) < 0 || intval($turnData['cell']) > 4) return new FailedTurn($game, []);

    $this->pushHistory($game, intval($turnData['cell']));

    $grid = (new ProvablyFair($this, $game->server_seed, $game->id))->result()->result()[$this->gameData($game)['mines'] - 1];
    $row = $grid[$this->getTurn($game) - 1];

    if (in_array(intval($turnData['cell']), $row)) {
      $this->pushData($game, ['grid' => $grid]);
      return new LoseGame($game, ['death' => $row, 'grid' => $grid]);
    }

    $game->update([
      'multiplier' => $this->data()[$this->gameData($game)['mines']][$this->getTurn($game)]
    ]);

    $this->pushData($game, [strval($this->getTurn($game)) => intval($turnData['cell'])]);

    if ($this->getTurn($game) >= 10) {
      $this->pushData($game, ['grid' => $grid]);
      return new FinishGame($game, ['death' => $row, 'grid' => $grid]);
    }
    return new ContinueGame($game, ['death' => $row]);
  }

  public function isLoss(ProvablyFairResult $result, \App\Models\Game $game, array $turnData): bool {
    /*if($this->getTurn($game) > 1) for($i = 1; $i < $this->getTurn($game); $i++) {
        if(in_array($this->gameData($game)[strval($i)], (new ProvablyFair($this, $result->server_seed()))->result()->result()[$this->gameData($game)['mines'] - 1][$i - 1])) return false;
    }*/
    return in_array(intval($turnData['cell']), (new ProvablyFair($this, $result->server_seed(), $game->id))->result()->result()[$this->gameData($game)['mines'] - 1][$this->getTurn($game)]);
  }

  function result(ProvablyFairResult $result): array {
    $output = [];
    $columns = 4;
    $rows = 10;
    for ($mines = 1; $mines <= 4; $mines++) {
      $row = [];
      for ($i = 1; $i <= $rows; $i++) {
        $array = range(0, $columns);
        $floats = $result->extractFloats($columns * $i);
        $floats = array_slice($floats, $columns * ($i - 1), $columns * $i);
        $index = -1;
        array_push($row, array_slice(array_map(function ($float) use (&$array, &$floats, &$mines, &$i, &$index, &$columns) {
          $index = $index + 1;
          return array_splice($array, floor($float * ($columns - $index + 1)), 1)[0] ?? -1;
        }, $floats), 0, $mines));
      }

      array_push($output, $row);
    }
    return $output;
  }

  public function data(): array {
    if (!Cache::has('phoenix:towerMultipliers:' . HouseEdgeModule::get($this, 1.00))) {
      $output = [
        1 => $this->applyHouseEdge([
          1 => 1.25,
          2 => 1.5625,
          3 => 1.9531,
          4 => 2.4414,
          5 => 3.0517,
          6 => 3.8146,
          7 => 4.7683,
          8 => 5.9604,
          9 => 7.4505,
          10 => 9.3131
        ]),
        2 => $this->applyHouseEdge([
          1 => 1.67,
          2 => 2.7889,
          3 => 4.65746,
          4 => 7.77796,
          5 => 12.9892,
          6 => 21.692,
          7 => 36.2256,
          8 => 60.4967,
          9 => 101.03,
          10 => 168.719
        ]),
        3 => $this->applyHouseEdge([
          1 => 2.5,
          2 => 6.25,
          3 => 15.625,
          4 => 39.0625,
          5 => 97.6562,
          6 => 244.141,
          7 => 610.352,
          8 => 1525.88,
          9 => 3814.7,
          10 => 9536.74
        ]),
        4 => $this->applyHouseEdge([
          1 => 5,
          2 => 25,
          3 => 125,
          4 => 625,
          5 => 3125,
          6 => 15625,
          7 => 78125,
          8 => 390625,
          9 => 1953125,
          10 => 9765625
        ])
      ];
      Cache::forever('phoenix:towerMultipliers:' . HouseEdgeModule::get($this, 1.00), json_encode($output));
    }
    return json_decode(Cache::get('phoenix:towerMultipliers:' . HouseEdgeModule::get($this, 1.00)), true);
  }

  public function multiplier(?Game $game, ?Data $data, ProvablyFairResult $result): float {
    return $this->data()[$this->gameData($game)['mines']][$this->getTurn($game) + 1];
  }

  public function getBotData(): array {
    return [
      'mines' => mt_rand(1, 4)
    ];
  }

  public function getBotTurnData($turnId): array {
    return [
      'cell' => mt_rand(0, 4)
    ];
  }

}
