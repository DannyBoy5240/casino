<?php

namespace App\Jobs;

//use App\Events\MultiplayerGameFinished;
//use App\Events\MultiplayerTimerStart;
//use App\Games\Crash;
//use App\Games\Kernel\Game;
//use App\Games\Kernel\Multiplayer\MultiplayerGameStateBuilder;
//use App\Games\Kernel\ProvablyFair;
//use App\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use App\Events\AutoCashoutGameResult;

class FinishCrashGame implements ShouldQueue {

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $game;
    private $multiplier;

    private $tries = 3;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(\App\Models\Game $game, $multiplier) {
       $this->onConnection('crash');
       $this->onQueue('crash_finish');
        $this->game = $game;
        $this->multiplier = $multiplier;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() {
        $lock = Cache::lock($this->game->_id . 'crash', 5);

  //      Log::info('Unutar finish crash gamea sam');

        if($lock->get()) {
            // not sure why is this updated when game record is updated below, probably preimpetively in case the game doesn't have user attached

            // TODO check if the game is already finished!
            // $this->game->update([
            //     'profit' => $this->game->status === 'lose' ? 0 : $this->game->wager * $this->game->multiplier,
            //     'status' => $this->game->status === 'in-progress' ? 'win' : $this->game->status
            // ]);

          //  Log::info('Unutar lock funkcije sam');
         //   Log::info($this->game->toArray());

            if($this->game != null && $this->game->user != null && $this->game->status === 'in-progress') {
                $currency = \App\Models\Currency::find($this->game->currency);
                $user = \App\Models\User::where('_id', $this->game->user)->first();

          //      Log::info('isplacujem dobitak');

                if($this->multiplier > 1000) $multiplier = 1;

                $this->game->update([
                    'status' => 'win',
                    'profit' => $this->game->wager * $this->multiplier,
                    'multiplier' => $this->multiplier
                ]);
                event(new AutoCashoutGameResult(\App\Models\User::where('_id', $this->game->user)->first(), $this->game));
                event(new \App\Events\MultiplayerBetCancellation($this->game, \App\Models\User::where('_id', $this->game->user)->first()));
                \App\Models\User::where('_id', $this->game->user)->first()->balance(\App\Currency\Currency::find($this->game->currency))->demo($this->game->demo)
                    ->add($this->game->profit, \App\Models\Transaction::builder()->message('Crash (Take)')->game('crash')->get());
                event(new \App\Events\LiveFeedGame($this->game, 0));
                \App\Games\Kernel\Game::analytics($this->game);

                Redis::zrem('crash', $this->game->_id); // so that the player doesn't get rewarded again
            }

            $lock->release(); // maybe unnecessary because lock would have been released after 5s anyway, and there's ~12s inbetween crash rounds
        }
    }

}