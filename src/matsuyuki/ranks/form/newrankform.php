<?php

namespace matsuyuki\ranks\form;

use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\form\Form;
use matsuyuki\ranks\rank;
use onebone\economyapi\EconomyAPI;

class newrankform implements Form {
    private rank $rank;
    private Player $player;
    private string $playername;
    private Config $cfrank;
    private Config $cfconfig;
    private string $err;
    private string $default;
    
    public function __construct(Player $player, rank $rank, string $err = "", string $default = "") {

        $datafolder = $rank->getDataFolder();
        $this->rank = $rank;
        $this->player = $player;
        $this->playername = $player->getName();
        $this->cfrank = new Config($datafolder. "rank.json", Config::JSON);
        $this->cfconfig = new Config($datafolder. "config.yml", Config::YAML, [
            "mkcost" => 100000,
            "len" => 10,
            "initrank" => "§a鯖民"
        ]);
        $this->cfconfig->reload();
        $this->err = $err;
        if ($err === "" && EconomyAPI::getInstance()->myMoney($player->getName()) < $this->cfconfig->get("mkcost")) {
            $this->err = "\n§l§cあなたは称号を作成できるだけのお金を持っていません。\n§l§cこのまま進んでも、称号は作成できません。";
        }
        $this->default = $default;

    }

    public function handleResponse(Player $player, $data):void {

        if ($data === null) {
            return;
        }
        $this->cfrank->reload();
        $playername = $player->getName();
        $rankdata = $this->cfrank->get($playername);
        if ($data[3] === false) {
            $player->sendForm(new newrankform($player, $this->rank,
             "\n§l§c称号を作成するには、支払い確認ボタンにチェックを入れてください。", $data[1]));
            return;
        }
        if (substr($data[1], -1) === "§") { //最後の文字列を取得する
            $player->sendForm(new newrankform($player, $this->rank,
            "\n§l§c称号の最後にセクション記号を置くことはできません。", $data[1]));
           return;
        }
        $ranklen = mb_strlen($data[1]) - (substr_count($data[1], "§") * 2) + (substr_count($data[1], "§§") * 2);
        if ($ranklen > $this->cfconfig->get("len")) { //§除いた文字列が指定文字以上
            $player->sendForm(new newrankform($player, $this->rank,
            "\n§l§c称号の長さが上限を越しています。(". $ranklen. ">". $this->cfconfig->get("len"). ")", $data[1]));
           return;
        }
        if ($ranklen == 0) { //称号の長さが0文字
            $player->sendForm(new newrankform($player, $this->rank,
            "\n§l§c空の称号を作ることはできません。", $data[1]));
           return;
        }
        if (EconomyAPI::getInstance()->myMoney($playername) < $this->cfconfig->get("mkcost")) {
            $player->sendForm(new newrankform($player, $this->rank,
            "\n§l§cあなたは称号を作成できるだけのお金を持っていません。", $data[1]));
           return;
        }
        $exist = false;
        foreach ($rankdata["history"] as $eachrank) {
           if ($eachrank === $data[1]) {
              $exist = true;
           }
        }
        if ($exist === true) {
            $player->sendForm(new newrankform($player, $this->rank,
            "\n§l§c既に同じ称号を作成済みです。", $data[1]));
           return;
        }
        array_unshift($rankdata["history"], $data[1]);
        EconomyAPI::getInstance()->reduceMoney($playername, $this->cfconfig->get("mkcost"));
        $this->cfrank->set($playername, $rankdata);
        $this->cfrank->save();
        if ($data[2] === true) {
            $player->setDisplayName("[". $data[1]. "§r] ". $playername);
            $player->setNameTag("[". $data[1]. "§r] ". $playername);
        }
        $player->sendMessage("§a【ranks】§f称号を作成しました！");
    }

    public function jsonSerialize() {

        $this->cfconfig->reload();
        $cost = $this->cfconfig->get("mkcost");
        $moneyprefix = EconomyAPI::getInstance()->getMonetaryUnit();
        return [
            "type" => "custom_form",
            "title" => "称号/新しい称号を作る",
            "content" => [
                [
                    "type" => "label",
                    "text" => "新しい称号を作ります。\n後から他の称号に変更した場合でも、この称号は無料で再度つけることができます。\n称号の最大文字数は". $this->cfconfig->get("len"). "文字です。". $this->err
                ],
                [
                    "type" => "input",
                    "text" => "作成する称号:",
                    "placeholder" => "作成する称号を入力...",
                    "default" => $this->default
                ],
                [
                    "type" => "toggle",
                    "text" => "現在の称号を作成した称号に変える",
                    "default" => false
                ],
                [
                    "type" => "toggle",
                    "text" => "(支払い確認)新しい称号を作成するには、ゲーム内マネー". $cost. $moneyprefix. "が必要であることを理解しています。",
                    "default" => false
                ]
            ]
        ];

    }
}