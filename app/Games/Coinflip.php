<?php namespace App\Games;

use App\Models\Game;
use App\Games\Kernel\Data;
use App\Games\Kernel\Extended\ContinueGame;
use App\Games\Kernel\Extended\ExtendedGame;
use App\Games\Kernel\Extended\LoseGame;
use App\Games\Kernel\Extended\Turn;
use App\Games\Kernel\GameCategory;
use App\Games\Kernel\Metadata;
use App\Games\Kernel\Module\General\HouseEdgeModule;
use App\Games\Kernel\Module\General\Wrapper\MultiplierCanBeLimited;
use App\Games\Kernel\ProvablyFair;
use App\Games\Kernel\ProvablyFairResult;

class Coinflip extends ExtendedGame implements MultiplierCanBeLimited {

  function metadata(): Metadata {
    return new class extends Metadata {
      function id(): string {
        return 'coinflip';
      }

      function name(): string {
        return 'Coinflip';
      }

      function icon(): string {
        return 'fas fa-coin';
      }

      public function category(): array {
        return [GameCategory::$originals];
      }

      public function image(): string {
        return "/img/game/coinflip.png";
      }
    };
  }

  public function start(\App\Models\Game $game) {}

  public function turn(\App\Models\Game $game, array $turnData): Turn {
    $color = $turnData['color'];
    $generatedColor = (new ProvablyFair($this, $game->server_seed, $game->id))->result()->result()[$this->getTurn($game) - 1];

    if ($color !== $generatedColor) return new LoseGame($game, ['color' => $generatedColor]);

    $multiplier = 2.0;
    for($i = 1; $i < $this->getTurn($game); $i++) $multiplier *= 2;

    // $game->update([
    //   'multiplier' => $this->getTurn($game) == 1 ? HouseEdgeModule::apply($this, 2.0) : $game->multiplier * 2
    // ]);

    $game->update([
      'multiplier' =>  HouseEdgeModule::apply($this, $multiplier)
    ]);

    $this->pushData($game, [intval($this->getTurn($game)) => $generatedColor]);

    $this->pushHistory($game, $color);
    return new ContinueGame($game, ['color' => $generatedColor]);
  }

  public function isLoss(ProvablyFairResult $result, \App\Models\Game $game, array $turnData): bool {
    /*if($this->getTurn($game) > 1) for($i = 1; $i < $this->getTurn($game); $i++) {
        if($result->result()[$i] !== $this->gameData($game)[strval($i)]) return false;
    }*/
    return $result->result()[$this->getTurn($game)] !== $turnData['color'];
  }

  function result(ProvablyFairResult $result): array {
    $directions = ['blue', 'yellow'];

    $output = [];
    for ($i = 0; $i < 52; $i++) array_push($output, $directions[floor($result->extractFloats(52)[$i] * 2)]);
    return $output;
  }

  public function multiplier(?Game $game, ?Data $data, ProvablyFairResult $result): float {
    return $this->getTurn($game) == 0 ? HouseEdgeModule::apply($this, 1.00) : $game->multiplier * 2;
  }

  public function getBotTurnData($turnId): array {
    return [
      'color' => mt_rand(0, 100) <= 50 ? 'blue' : 'yellow'
    ];
  }

}
