<?php

namespace matsuyuki\ranks\form;

use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\form\Form;
use matsuyuki\ranks\rank;
use matsuyuki\ranks\form\changerankform;
use matsuyuki\ranks\form\newrankform;
use onebone\economyapi\EconomyAPI;

class menuform implements Form {
    
    public function __construct(Player $player, rank $rank) {

        $datafolder = $rank->getDataFolder();
        $this->rank = $rank;
        $this->player = $player;
        $this->playername = $player->getName();
        $this->cfrank = new Config($datafolder. "rank.json", Config::JSON);

    }

    public function handleResponse(Player $player, $data):void {

        if ($data === null) {
            return;
        }
        switch ($data) {
            case 0: //履歴から称号を変更
                if (!$player->hasPermission("ranks.changerank")) {
                    $player->sendMessage("§c【ranks:Err】§f実行権限がありません");
                    return;
                }
                $player->sendForm(new changerankform($player, $this->rank));
                break;
            case 1: //称号を新規作成
                if (!$player->hasPermission("ranks.mkoriginalrank")) {
                    $player->sendMessage("§c【ranks:Err】§f実行権限がありません");
                    return;
                }
                $player->sendForm(new newrankform($player, $this->rank));
                break;
        }
        
    }

    public function jsonSerialize() {

        $this->cfrank->reload();
        $rank = $this->cfrank->get($this->playername);
        $rank = $rank["now"];
        return [
            "type" => "form",
            "title" => "称号/メニュー画面",
            "content" => "§7現在のテキストサンプル: §f<[". $rank. "§r§f]". $this->playername. "§r§f> テキスト\n§7所持金: §f"
            . EconomyAPI::getInstance()->myMoney($this->playername). EconomyAPI::getInstance()->getMonetaryUnit(),
            "buttons" => [
                ["text" => "§l履歴から称号を変更"],
                ["text" => "§l称号を新規作成"]
            ]
        ];
    }
}