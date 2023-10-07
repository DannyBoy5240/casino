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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Games\Kernel\Module\General\HouseEdgeModule;


class Stairs extends ExtendedGame implements MultiplierCanBeLimited {

  function metadata(): Metadata {
    return new class extends Metadata {
      function id(): string {
        return 'stairs';
      }

      function name(): string {
        return 'Stairs';
      }

      function icon(): string {
        return 'stairs';
      }

      public function category(): array {
        return [GameCategory::$originals];
      }
    };
  }

  public function start(\App\Models\Game $game) {
    $this->pushData($game, ['mines' => intval($this->userData($game)['data']['mines'])]);
  }

  public function getModuleData(\App\Models\Game $game) {
    return floatval($this->gameData($game)['mines']);
  }

  public function turn(\App\Models\Game $game, array $turnData): Turn {
    $rows = [20, 19, 18, 17, 16, 15, 14, 13, 12, 11, 10, 9, 8];
    $row = $rows[$this->getTurn($game) - 1];
    if (intval($turnData['cell']) >= $row || intval($turnData['cell']) < 0) return new FailedTurn($game, []);

    $this->pushHistory($game, intval($turnData['cell']));

    $row = (new ProvablyFair($this, $game->server_seed, $game->id))->result()->result()[$this->gameData($game)['mines'] - 1][$this->getTurn($game) - 1];
    if (in_array(intval($turnData['cell']), $row)) return new LoseGame($game, ['death' => $row]);

    $game->update([
      'multiplier' => $this->data()[$this->gameData($game)['mines']][$this->getTurn($game)]
    ]);

    $this->pushData($game, [strval($this->getTurn($game)) => intval($turnData['cell'])]);

    if ($this->getTurn($game) >= 13) return new FinishGame($game, ['death' => $row]);
    return new ContinueGame($game, ['death' => $row]);
  }

  public function isLoss(ProvablyFairResult $result, \App\Models\Game $game, array $turnData): bool {
    /*if($this->getTurn($game) > 1) for($i = 1; $i < $this->getTurn($game); $i++) {
        if(in_array($this->gameData($game)[strval($i)], (new ProvablyFair($this, $result->server_seed()))->result()->result()[$this->gameData($game)['mines'] - 1][$i - 1])) return false;
    }*/
    return in_array(intval($turnData['cell']), (new ProvablyFair($this, $result->server_seed(), $game->id))->result()->result()[$this->gameData($game)['mines'] - 1][$this->getTurn($game)]);
  }

  function result(ProvablyFairResult $result): array {
    $rows = [20, 19, 18, 17, 16, 15, 14, 13, 12, 11, 10, 9, 8];
    $output = [];
    for ($mines = 1; $mines <= 7; $mines++) {
      $row = [];
      for ($i = 1; $i <= count($rows); $i++) {
        $array = range(0, $rows[$i - 1]);
        $floats = $result->extractFloats($rows[$i - 1] * $i);
        $floats = array_splice($floats, $i - 1, $mines * $i);
        $index = 0;
        array_push($row, array_slice(array_map(function ($float) use (&$array, &$floats, &$mines, &$rows, &$i, &$index) {
          $index = $index + 1;
          return array_splice($array, floor($float * ($rows[$i - 1] - $index + 1)), 1)[0] ?? 5;
        }, $floats), 0, $mines));
      }
      array_push($output, $row);
    }
    return $output;
  }

  public function data(): array {
    if (!Cache::has('phoenix:stairsMultipliers:' . HouseEdgeModule::get($this, 0))) {
      $output =  [
        1 => $this->applyHouseEdge([
          13 => 2.71 * 1.05,
          12 => 2.38 * 1.05,
          11 => 2.11 * 1.05,
          10 => 1.90 * 1.05,
          9 => 1.73 * 1.05,
          8 => 1.58 * 1.05,
          7 => 1.46 * 1.05,
          6 => 1.36 * 1.05,
          5 => 1.27 * 1.05,
          4 => 1.19 * 1.05,
          3 => 1.12 * 1.05,
          2 => 1.06 * 1.05,
          1 => 1.00 * 1.05,
        ]),
        2 => $this->applyHouseEdge([
          13 => 8.60 * 1.05,
          12 => 6.45 * 1.05,
          11 => 5.01 * 1.05,
          10 => 4.01 * 1.05,
          9 => 3.28 * 1.05,
          8 => 2.73 * 1.05,
          7 => 2.31 * 1.05,
          6 => 1.98 * 1.05,
          5 => 1.72 * 1.05,
          4 => 1.50 * 1.05,
          3 => 1.33 * 1.05,
          2 => 1.18 * 1.05,
          1 => 1.06 * 1.05
        ]),
        3 => $this->applyHouseEdge([
          13 => 30.94 * 1.05,
          12 => 19.34 * 1.05,
          11 => 12.89 * 1.05,
          10 => 9.03 * 1.05,
          9 => 6.56 * 1.05,
          8 => 4.92 * 1.05,
          7 => 3.79 * 1.05,
          6 => 2.98 * 1.05,
          5 => 2.38 * 1.05,
          4 => 1.93 * 1.05,
          3 => 1.59 * 1.05,
          2 => 1.33 * 1.05,
          1 => 1.12 * 1.05
        ]),
        4 => $this->applyHouseEdge([
          13 => 131.51 * 1.05,
          12 => 65.75 * 1.05,
          11 => 36.53 * 1.05,
          10 => 21.92 * 1.05,
          9 => 13.95 * 1.05,
          8 => 9.30 * 1.05,
          7 => 6.44 * 1.05,
          6 => 4.60 * 1.05,
          5 => 3.37 * 1.05,
          4 => 2.53 * 1.05,
          3 => 1.93 * 1.05,
          2 => 1.50 * 1.05,
          1 => 1.19 * 1.05
        ]),
        5 => $this->applyHouseEdge([
          13 => 701.37 * 1.05,
          12 => 263.01 * 1.05,
          11 => 116.90 * 1.05,
          10 => 58.45 * 1.05,
          9 => 31.88 * 1.05,
          8 => 18.60 * 1.05,
          7 => 11.44 * 1.05,
          6 => 7.36 * 1.05,
          5 => 4.90 * 1.05,
          4 => 3.37 * 1.05,
          3 => 2.38 * 1.05,
          2 => 1.72 * 1.05,
          1 => 1.27 * 1.05
        ]),
        6 => $this->applyHouseEdge([
          13 => 5260.29 * 1.05,
          12 => 1315.07 * 1.05,
          11 => 438.36 * 1.05,
          10 => 175.34 * 1.05,
          9 => 79.70 * 1.05,
          8 => 39.85 * 1.05,
          7 => 21.46 * 1.05,
          6 => 12.26 * 1.05,
          5 => 7.36 * 1.05,
          4 => 4.60 * 1.05,
          3 => 2.98 * 1.05,
          2 => 1.98 * 1.05,
          1 => 1.36 * 1.05
        ]),
        7 => $this->applyHouseEdge([
          13 => 73644.00 * 1.05,
          12 => 9205.00 * 1.05,
          11 => 2045.67 * 1.05,
          10 => 613.70 * 1.05,
          9 => 223.16 * 1.05,
          8 => 92.98 * 1.05,
          7 => 42.92 * 1.05,
          6 => 21.46 * 1.05,
          5 => 11.44 * 1.05,
          4 => 6.44 * 1.05,
          3 => 3.79 * 1.05,
          2 => 2.31 * 1.05,
          1 => 1.46 * 1.05
        ])
      ];
      Cache::forever('phoenix:stairsMultipliers:' . HouseEdgeModule::get($this, 0), json_encode($output));
    }
    return json_decode(Cache::get('phoenix:stairsMultipliers:' . HouseEdgeModule::get($this, 0)), true);
  }

  public function multiplier(?Game $game, ?Data $data, ProvablyFairResult $result): float {
    return $this->data()[$this->gameData($game)['mines']][$this->getTurn($game)];
  }

  public function getBotData(): array {
    return [
      'mines' => mt_rand(1, 7)
    ];
  }

  public function getBotTurnData($turnId): array {
    return [
      'cell' => mt_rand(0, 19)
    ];
  }

}
