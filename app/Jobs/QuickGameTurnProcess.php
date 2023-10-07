<?php

namespace App\Jobs;

use App\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Games\Kernel\Data;
use App\Games\Kernel\ProvablyFairResult;
use Illuminate\Support\Facades\Log;
use Throwable;
use App\Leaderboard;
use App\Events\LiveFeedGame;
use Illuminate\Support\Facades\DB;
use App\Currency\Currency;
use App\Transaction;
use App\User;
use App\Events\BalanceModification;
use App\Games\Kernel\Game;

class QuickGameTurnProcess implements ShouldQueue {

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	private $game;
    private $data;
    private $result;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Game $game, Data $data, $result) {
        $this->game = $game;
        $this->data = $data;
        $this->result = $result;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() {
		if(!isSet($this->result['code']))
			$result = $this->result['response'];
		
		$t1 = microtime(true);
		Log::info('QUICK MARGINAL PROCESS START');
		
        if($this->data->user() != null && $this->data->user()->referral != null && $this->data->user()->games() >= floatval(Settings::get('referrer_activity_requirement', 100))) {
            $referrer = User::where('_id', $this->data->user()->referral)->first();
            $referrals = $referrer->referral_wager_obtained ?? [];
            if(!in_array($this->data->user()->_id, $referrals)) {
                array_push($referrals, $this->data->user()->_id);
                $referrer->update(['referral_wager_obtained' => $referrals]);
                $referrer->balance(Currency::find('btc'))->add(floatval(Currency::find('btc')->option('referral_bonus')), Transaction::builder()->message('Active referral bonus')->get());
            }
        }

        if($this->data->user() != null && $this->data->user()->vipLevel() > 0 && $this->data->user()->vip_discord_notified == null) {
            $this->data->user()->notify(new \App\Notifications\VipDiscordNotification());
            $this->data->user()->update(['vip_discord_notified' => true]);
        }		
		
        if(!isset($result['code']) && !$this->data->guest()) {
            $this->data->user()->balance(Currency::find($this->data->currency()))->demo($this->data->demo())->quiet()->subtract($this->data->bet(), Transaction::builder()->game($this->game->metadata()->id())->message('Game')->get());

            if ($result['game']['profit'] == 0) event(new BalanceModification($this->data->user(), Currency::find($this->data->currency()), 'subtract', $this->data->demo(), $this->data->bet(), $result['game']['delay']));
            else {
                if ($result['game']['multiplier'] < 1) event(new BalanceModification($this->data->user(), Currency::find($this->data->currency()), 'subtract', $this->data->demo(), $result['game']['profit'], $result['game']['delay']));
                else event(new BalanceModification($this->data->user(), Currency::find($this->data->currency()), 'add', $this->data->demo(), $result['game']['profit'] - $this->data->bet(), $result['game']['delay']));
            }

            if (!$this->data->demo() && $this->data->user()->vipLevel() > 0 && ($this->data->user()->weekly_bonus ?? 0) < 100) $this->data->user()->update(['weekly_bonus' => ($this->data->user()->weekly_bonus ?? 0) + 0.1]);
        } else {
			Log::info('[1] QUICK MARGINAL PROCESS COMPLETED IN '.$time.'s');
			return;
		};

        if(!$this->data->demo()) {
            $game = \App\Game::create([
                'id' => DB::table('games')->count() + 1,
                'user' => $this->data->user()->_id,
                'game' => $this->game->metadata()->id(),
                'wager' => $this->data->bet(),
                'multiplier' => $result['game']['multiplier'],
                'status' => $result['game']['profit'] > 0 ? ($result['game']['multiplier'] < 1 ? 'lose' : 'win') : 'lose',
                'profit' => $result['game']['profit'],
                'server_seed' => $result['server_seed']['server_seed'],
                'client_seed' => $result['server_seed']['client_seed'],
                'nonce' => $result['server_seed']['nonce'],
                'data' => $result['game']['data'],
                'type' => 'quick',
                'currency' => $this->data->currency()
            ]);

            event(new LiveFeedGame($game, $result['game']['delay']));

            Leaderboard::insert($game);
        }
		
		$t4 = microtime(true);
		$time = $t4-$t1;
		Log::info('[2] QUICK MARGINAL PROCESS COMPLETED IN '.$time.'s');
    }

}
