<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use App\Events\AutoCashoutGameResult;
use App\Games\Kernel\Data;
use App\Models\Leaderboard;
use Illuminate\Support\Facades\DB;
use App\Currency\Currency;

class SaveQuickGameResult implements ShouldQueue {

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Data $data;
    private $client_seed;
    private $game;
    private $user;
    private $result;
    private $db_data;


    private $tries = 3;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Data $data, $user, $game, $result, $db_data, $client_seed) {
        $this->data = $data;
        $this->user = $user;
        $this->game = $game;
        $this->result = $result;
        $this->db_data = $db_data;
        $this->client_seed = $client_seed;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() {
        if($this->user != null && $this->user->referral != null && $this->user->games() >= floatval(\App\Models\Settings::get('referrer_activity_requirement', 100))) {
            $referrer = \App\Models\User::where('_id', $this->user->referral)->first();
            $referrals = $referrer->referral_wager_obtained ?? [];
            if(!in_array($this->user->_id, $referrals)) {
                array_push($referrals, $this->user->_id);
                $referrer->update(['referral_wager_obtained' => $referrals]);
                $referrer->balance(Currency::find('btc'))->add(floatval(Currency::find('btc')->option('referral_bonus')), \App\Models\Transaction::builder()->message('Active referral bonus')->get());
            }
        } 

        // if($this->user != null && $this->user->vipLevel() > 0 && $this->user->vip_discord_notified == null) {
        //     $this->user->notify(new \App\Notifications\VipDiscordNotification());
        //     $this->user->update(['vip_discord_notified' => true]);
        // }

        if(!$this->data->demo()) {

            $game = \App\Models\Game::create([
                'id' => DB::table('games')->count() + 1,
                'user' => $this->data->user()->_id,
                'game' => $this->game,
                'wager' => $this->data->bet(),
                'multiplier' => $this->result['response']['game']['multiplier'],
                'status' => $this->result['response']['game']['profit'] > 0 ? ($this->result['response']['game']['multiplier'] < 1 ? 'lose' : 'win') : 'lose',
                'profit' => $this->result['response']['game']['profit'],
                'server_seed' => $this->result['response']['server_seed']['server_seed'],
                'client_seed' => $this->client_seed,
                'nonce' => $this->result['response']['server_seed']['nonce'],
                'data' => $this->db_data,
                'type' => 'quick',
                'currency' => $this->data->currency(),
                'bet_usd_converted' => Currency::find($this->data->currency())->convertTokenToFiat($this->data->bet())
            ]);

            event(new \App\Events\LiveFeedGame($game, $this->result['response']['game']['delay']));

            Leaderboard::insert($game);

            \App\Games\Kernel\Game::analytics($game);
        }
    }

}