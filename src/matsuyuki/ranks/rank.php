<?php

namespace matsuyuki\ranks; 

use pocketmine\block\BaseSign;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener; 
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\event\block\SignChangeEvent;
use onebone\economyapi\EconomyAPI;
use pocketmine\block\utils\SignText;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerInteractEvent;
use matsuyuki\ranks\form\changerankform;
use matsuyuki\ranks\form\menuform;
use matsuyuki\ranks\form\newrankform;

class rank extends PluginBase implements Listener {
    private Config $cfrank;
    private Config $cfconfig;
    private Config $cfshop;
    private array $confirm;

   public function onEnable():void {

      $this->getServer()->getPluginManager()->registerEvents($this, $this);
      $this->cfrank = new Config($this->getDataFolder() ."rank.json", Config::JSON);
      $this->cfconfig = new Config($this->getDataFolder() ."config.yml", Config::YAML, [
         "mkcost" => 100000,
         "len" => 10,
         "initrank" => "§a鯖民"
      ]);
      $this->cfshop = new Config($this->getDataFolder() ."rankshop.json", Config::JSON, [
         "shopdata" => []
      ]);
      $this->confirm = [];

   }

   public function onJoin(PlayerJoinEvent $event) {

      $player = $event->getPlayer();
      $playername = $player->getName();
      $this->cfrank->reload();
      $this->cfconfig->reload();
      $config = $this->cfconfig->getAll();
      $cfrank = $this->cfrank->getAll();
      if (!array_key_exists($playername, $cfrank)) { //初参加
         $cfrank[$playername]["now"] = $config["initrank"];
         $cfrank[$playername]["history"][] = $config["initrank"];
         $this->cfrank->set($playername, $cfrank[$playername]);
         $this->cfrank->save();
         $this->cfrank->reload();
         $cfrank = $this->cfrank->getAll();
      }
      $player->setDisplayName("[". $cfrank[$playername]["now"]. "§r] ". $playername);
      $player->setNameTag("[". $cfrank[$playername]["now"]. "§r] ". $playername);

   }

   public function onSignChange(SignChangeEvent $event) {

      if ($event->getNewText()->getLine(3) === "mkrankshop") {

         if (!$event->getPlayer()->hasPermission("ranks.mkrankshop")) { //作成者が権限を持っていない
            $event->getPlayer()->sendMessage("§c【ranks:Err】§fあなたはこの看板を作る権限を持っていません");
            return;
         }
         $block = $event->getBlock();
         $rank = $event->getNewText()->getLine(0);
         $price = $event->getNewText()->getLine(1);
         $message = $event->getNewText()->getLine(2);
         if (!is_numeric($price)) { //費用が数値じゃない
            $event->getPlayer()->sendMessage("§c【ranks:Err】§f費用(看板2行目)は数値である必要があります");
            return;
         }
         $position = $block->getPosition()->getX(). ":". $block->getPosition()->getY(). ":".
            $block->getPosition()->getZ(). ":". $block->getPosition()->getWorld()->getFolderName();
         $rankshopdata = [
            "rank" => $rank,
            "price" => $price,
            "message" => $message,
            "creator" => $event->getPlayer()->getName(),
            "position" => $position
         ];
         $this->cfshop->reload();
         $allrankshopdata = $this->cfshop->get("shopdata");
         foreach ($allrankshopdata as $index => $eachshopdata) { //shopdataが重複してたら削除
            if ($rankshopdata["position"] === $eachshopdata["position"]) {
               unset($allrankshopdata[$index]);
               $allrankshopdata = array_values($allrankshopdata);
            }
         }
         $allrankshopdata[] = $rankshopdata;
         $this->cfshop->set("shopdata", $allrankshopdata);
         $this->cfshop->save();
         $signcontents = [
            "§l§b【称号ショップ】",
            $rank,
            "§l§0価格: §b". $price. " ".EconomyAPI::getInstance()->getMonetaryUnit(),
            "§l§a看板タップで購入!"
         ];
         $event->setNewText(new SignText($signcontents));
         
      }

   }

   public function onTap(PlayerInteractEvent $event):void {

      $block = $event->getBlock();
      if (!$block instanceof BaseSign) { //タップしたブロックが看板じゃないなら返す
         return;
      }
      $player = $event->getPlayer();
      $playername = $player->getName();
      $position = $block->getPosition()->getX(). ":". $block->getPosition()->getY(). ":".
      $block->getPosition()->getZ(). ":". $block->getPosition()->getWorld()->getFolderName();
      $this->cfshop->reload();
      $shopdatas = $this->cfshop->get("shopdata"); //全部のショップデータ
      $shopdata = null; //看板のショップデータ
      foreach ($shopdatas as $eachshopdata) {
         if ($eachshopdata["position"] === $position) {
            $shopdata = $eachshopdata;
         }
      }
      if ($shopdata === null) { //タップした看板が称号の物ではないなら返す
         return;
      }
      if (!array_key_exists($playername, $this->confirm)) { //もし一度も確認してないなら
         $this->confirm[$playername] = 1; //1970年1月1日00:00:01に確認したことにする
      }
      if (time() - $this->confirm[$playername] > 10) { //最後の確認から10秒以上が経過
         $player->sendMessage("§a§l【ranks:購入確認】");
         $player->sendMessage("§7称号名: §r". $shopdata["rank"]);
         $player->sendMessage("§7値段: §b". $shopdata["price"]. EconomyAPI::getInstance()->getMonetaryUnit());
         $player->sendMessage("§7作成者: §b". $shopdata["creator"]);
         $player->sendMessage("§f称号を購入する場合は、再度看板をタップしてください。");
         $this->confirm[$playername] = time(); //確認時間を更新
         return;
      }

      $this->cfrank->reload();
      $rankdata = $this->cfrank->get($playername);
      $exist = false;
      foreach ($rankdata["history"] as $eachrank) {
         if ($eachrank === $shopdata["rank"]) {
            $exist = true;
         }
      }
      if ($exist === true) {
         $player->sendMessage("§c【ranks:Err】§fあなたはすでにこの称号を持っています。");
         return;
      }
      array_unshift($rankdata["history"], $shopdata["rank"]);
      EconomyAPI::getInstance()->reduceMoney($playername, $shopdata["price"]);
      $this->cfrank->set($playername, $rankdata);
      $this->cfrank->save();
      $player->sendMessage("§a【ranks】§f称号を購入しました！");
      if (mb_strlen($shopdata["message"]) != 0) {
         $player->sendMessage("§7作成者からのメッセージ: §f". $shopdata["message"]);
      }

   }

   public function onBreak(BlockBreakEvent $event):void {

      $block = $event->getBlock();
      if (!$block instanceof BaseSign) { //タップしたブロックが看板じゃないなら返す
         return;
      }
      $player = $event->getPlayer();
      $playername = $player->getName();
      $position = $block->getPosition()->getX(). ":". $block->getPosition()->getY(). ":".
      $block->getPosition()->getZ(). ":". $block->getPosition()->getWorld()->getFolderName();
      $this->cfshop->reload();
      $shopdatas = $this->cfshop->get("shopdata"); //全部のショップデータ
      $delindex = null; //削除するデータのインデックス
      foreach ($shopdatas as $index => $eachshopdata) { 
         if ($position === $eachshopdata["position"]) {
            $delindex = $index;
         }
      }
      if ($delindex === null) { //壊した看板が称号の物ではないなら返す
         return;
      }
      if (!$player->hasPermission("ranks.mkrankshop")) { //破壊者が権限を持っていない
         $player->sendMessage("§c【ranks:Err】§fあなたはこの看板を壊す権限を持っていません。");
         $event->cancel();
         return;
      } else {
         unset($shopdatas[$index]);
         $shopdatas = array_values($shopdatas);
         $this->cfshop->set("shopdata", $shopdatas);
         $this->cfshop->save;
         $player->sendMessage("§a【ranks】§f称号看板を削除しました。");
      }

   }

   public function onCommand(CommandSender $sender, Command $command, string $label, array $args):bool {
     
      switch ($command->getName()) {
         case "mkrank":

            if (!$sender instanceof Player) {
               $sender->sendMessage("§c【ranks:Err】§fコンソールからは実行できません");
               return false;
            }
            if (!$sender->hasPermission("ranks.mkoriginalrank")) {
               $sender->sendMessage("§c【ranks:Err】§f実行権限がありません");
               return false;
            }
            $sender->sendForm(new newrankform($sender, $this));
            return true;

         case "chgrank":

            if (!$sender instanceof Player) {
               $sender->sendMessage("§c【ranks:Err】§fコンソールからは実行できません");
               return false;
            }
            if (!$sender->hasPermission("ranks.changerank")) {
               $sender->sendMessage("§c【ranks:Err】§f実行権限がありません");
               return false;
            }
            $sender->sendForm(new changerankform($sender, $this));
            return true;

         case "rank":

            if (!$sender instanceof Player) {
               $sender->sendMessage("§c【ranks:Err】§fコンソールからは実行できません");
               return false;
            }
            if (!$sender->hasPermission("ranks.usemenuform")) {
               $sender->sendMessage("§c【ranks:Err】§f実行権限がありません");
               return false;
            }
            $sender->sendForm(new menuform($sender, $this));
            return true;

         case "chgrankcost":

            if (!$sender->hasPermission("ranks.changecost")) {
               $sender->sendMessage("§c【ranks:Err】§f実行権限がありません");
               return false;
            }
            if (empty($args[0])) {
               $sender->sendMessage("§c【ranks:Err】§f/chgrankcost <費用> のように使用してください。");
               return false;
            }
            if (!is_numeric($args[0])) {
               $sender->sendMessage("§c【ranks:Err】§fあなたが入力した文字(". $args[0]. ")は、数値に変換できないか大きすぎます。半角数字で入力してください。");
               return false;
            }
            if ((int) $args[0] < 0) {
               $sender->sendMessage("§c【ranks:Err】§f費用を0未満にすることはできません(". $args[0]. "<0)。");
               return false;
            }
            $this->cfconfig->reload();
            $oldmkcost = $this->cfconfig->get("mkcost");
            $this->cfconfig->set("mkcost", (int) $args[0]);
            $this->cfconfig->save();
            $sender->sendMessage("§a【ranks】§f作成費用を変更しました！(". (string) $oldmkcost. "->". $args[0]. ")");
            return true;

      }
      return false;

   }

}
