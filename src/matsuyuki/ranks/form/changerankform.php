<?php

namespace matsuyuki\ranks\form;

use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\form\Form;
use matsuyuki\ranks\rank;

class changerankform implements Form {
    private rank $rank;
    private Player $player;
    private string $playername;
    private Config $cfrank;
    private string $err;
    
    public function __construct(Player $player, rank $rank, string $err = "") {

        $datafolder = $rank->getDataFolder();
        $this->rank = $rank;
        $this->player = $player;
        $this->playername = $player->getName();
        $this->cfrank = new Config($datafolder. "rank.json", Config::JSON);
        $this->err = $err;

    }

    public function handleResponse(Player $player, $data):void {

        if ($data === null) {
            return;
        }
        $this->cfrank->reload();
        $playername = $player->getName();
        $rankdata = $this->cfrank->get($this->playername);
        $ranks = $rankdata["history"];
        if (count($ranks) === 0) {
            return;
        } else {
            $rank = $ranks[$data];
            $rankdata["now"] = $rank;
            $player->setDisplayName("[". $rank. "§r] ". $playername);
            $player->setNameTag("[". $rank. "§r] ". $playername);
            $this->cfrank->set($playername, $rankdata);
            $this->cfrank->save();
            $player->sendMessage("§a【ranks】§f称号を変更しました！");
        }
        
    }

    public function jsonSerialize() {

        $this->cfrank->reload();
        $ranks = $this->cfrank->get($this->playername);
        $ranks = $ranks["history"];
        if (count($ranks) === 0) {

            return [
                "type" => "form",
                "title" => "称号/称号を変更する",
                "content" => "§c§l称号がありません",
                "buttons" => [
                    ["text" => "§l閉じる"]
                ]
            ];

        } else {

            $buttons = [];
            foreach ($ranks as $rank) {
                $buttons[] = ["text" => $rank];
            }
            return [
                "type" => "form",
                "title" => "称号/称号を変更する",
                "content" => "変更する称号を選択してください",
                "buttons" => $buttons
            ];

        }

    }
}