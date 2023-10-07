<?php

namespace App\Jobs;

use App\Events\MultiplayerGameFinished;
use App\Events\MultiplayerTimerStart;
use App\Events\MultiplayerDataUpdate;
use App\Games\Crash;
use App\Games\Kernel\Game;
use App\Games\Kernel\Multiplayer\MultiplayerGameStateBuilder;
use App\Games\Kernel\ProvablyFair;
use App\Models\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class MultiplayerCrashUpdateData implements ShouldQueue {

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;



    private \App\Games\Kernel\Game $game;
	private $multiplier;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(\App\Games\Kernel\Game $game, $multiplier) {
    	$this->onConnection('crash');
    	$this->onQueue('crash_tick');
        $this->game = $game;
		$this->multiplier = $multiplier;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() {
		try{			
			$state = new MultiplayerGameStateBuilder($this->game);
			
			$timeInMilliseconds = 0;
			$oldTimeInMilliseconds = 0;
			$simulation = 1; $suS =  0;

			while($simulation < $this->multiplier) {
				$simulation += 0.05 / 15 + $suS;
				$timeInMilliseconds += 2000 / 15 / 3;
				if($simulation >= 5.5) {
					$suS += 0.05 / 15;
					$timeInMilliseconds += 4000 / 15 / 3;
				}
				
				$data = ['tick' => time(), 'multiplier' => $simulation, 'sign' => $this->multiplier];
				$state->data($data);
				event(new MultiplayerDataUpdate($this->game, $data));	
				$diff = $timeInMilliseconds - $oldTimeInMilliseconds;

				$t1 = microtime(true);

				$payouts = Redis::zRangeByScore('crash', 0, floor(floatval($simulation * 100)), ['withscores' => TRUE]);
				if($payouts) {
					// for($i = 0; $i < count($payouts); $i+= 2) {
					// 	Log::info('Isplacuejm igru: ' . $payouts[$i]);
					// 	Log::info('Multiplier je: ' . $payouts[$i + 1]);
					// 	$game = \App\Game::where('_id', $payouts[$i])->first();
					// 	Redis::zrem('crash', $payouts[$i]);
				 //        if(!is_null($game)){ 
					// 		dispatch(new FinishCrashGame($game, $payouts[$i+1] / 100));
					// 	}
					// }
					foreach($payouts as $id => $multiplier) {
						Log::info('Isplacuejm igru: ' . $id);
						Log::info('Multiplier je: ' . $multiplier);
						$game = \App\Models\Game::where('_id', $id)->first();
						Redis::zrem('crash', $id);
				        if(!is_null($game)){ 
							dispatch(new FinishCrashGame($game, $multiplier / 100));
						}
					}
				}

				$t2 = microtime(true);

				usleep($diff * 1000 - ($t2 - $t1));
				$oldTimeInMilliseconds = $timeInMilliseconds;
				
				if($simulation >= $this->multiplier){
					break;
				}
			}
			
			$data = ['tick' => time(), 'multiplier' => $this->multiplier, 'sign' => $this->multiplier];
			$state->data($data);
			event(new MultiplayerDataUpdate($this->game, $data));
			dispatch((new MultiplayerFinishAndSetupNextGame($this->game, ['multiplier' => $this->multiplier], now()->addSeconds(6))));			
		} catch (Exception $e) {
			$data = ['tick' => time(), 'multiplier' => $this->multiplier, 'sign' => $this->multiplier];
			$state->data($data);
			event(new MultiplayerDataUpdate($this->game, $data));
			dispatch((new MultiplayerFinishAndSetupNextGame($this->game, ['multiplier' => $this->multiplier], now()->addSeconds(6)))->delay(now()->addMilliseconds(100)));			
		}
    }

}