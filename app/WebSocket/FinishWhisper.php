<?php namespace App\WebSocket;

use App\Games\Kernel\Extended\ExtendedGame;
use App\Games\Kernel\Game;
use Illuminate\Support\Facades\Cache;


class FinishWhisper extends WebSocketWhisper {

    public function event(): string {
        return 'Finish';
    }

    public function process($data): array {
        // $game = \App\Models\Game::where('_id', $data->id)->first();
        // if($game == null) return ['code' => 1, 'message' => 'Invalid game id'];
        // if($game->status !== 'in-progress') return ['code' => 2, 'message' => 'Game is finished'];

        // $api_game = Game::find($game->game);
        // if(!($api_game instanceof ExtendedGame)) return ['code' => 3, 'message' => 'Unsupported game operation'];

        // $api_game->finish($game);
        // return [
        //     'game' => $game->toArray()
        // ];
        $game = \App\Models\Game::where('_id', $data->id)->first(); // frontend sent this id so that the game record from db can be pulled
  
        if($game == null) return ['code' => 1, 'message' => 'Invalid game id'];

        if($game->game == 'crash') {
            $lock = Cache::lock($data->id . 'crash', 5);
        }

        if(($game->game == 'crash' && $lock->get()) || $game->game != 'crash') {
            if($game->status !== 'in-progress') return ['code' => 2, 'message' => 'Game is finished']; 

            $api_game = Game::find($game->game);
            if(!($api_game instanceof ExtendedGame)) return ['code' => 3, 'message' => 'Unsupported game operation'];

            $api_game->finish($game);

            if($game->game == 'crash') {
                $lock->release();
            }

            return [
                'game' => $game->toArray()
            ];
        }
    }

}
