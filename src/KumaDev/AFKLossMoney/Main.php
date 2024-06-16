<?php

namespace KumaDev\AFKLossMoney;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\Task;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use DaPigGuy\libPiggyEconomy\libPiggyEconomy;
use DaPigGuy\libPiggyEconomy\providers\EconomyProvider;
use DaPigGuy\libPiggyEconomy\exceptions\MissingProviderDependencyException;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

class Main extends PluginBase implements Listener {

    /** @var EconomyProvider|null */
    private static $economyProvider;

    /** @var Config */
    private $config;

    /** @var array */
    private $afkPlayers = [];

    public function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        // Check for required dependencies
        foreach ([
            "libPiggyEconomy" => libPiggyEconomy::class
        ] as $virion => $class) {
            if (!class_exists($class)) {
                $this->getLogger()->error("[AFKLossMoney] " . $virion . " virion not found. Please download DeVirion Now!.");
                $this->getServer()->getPluginManager()->disablePlugin($this);
                return;
            }
        }

        // Initialize economy library
        libPiggyEconomy::init();
        
        // Load and save configuration file
        $this->saveResource("config.yml");
        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $economyConfig = $this->config->get("economy", null);

        // Ensure economy provider is set in the config
        if ($economyConfig === null || !isset($economyConfig['type'])) {
            $this->getLogger()->error("[AFKLossMoney] No economy provider specified in config.yml.");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        // Initialize economy provider
        try {
            self::$economyProvider = libPiggyEconomy::getProvider([
                'provider' => $economyConfig['type']
            ]);
        } catch (MissingProviderDependencyException $e) {
            $this->getLogger()->error("[AFKLossMoney] Dependencies for provider not found: " . $e->getMessage());
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        // Schedule AFK check task
        $this->getScheduler()->scheduleRepeatingTask(new class($this) extends Task {
            private $main;

            public function __construct(Main $main) {
                $this->main = $main;
            }

            public function onRun(): void {
                $this->main->checkAFKPlayers();
            }
        }, $this->config->get("afk_check_interval", 300)); // Default 5 minutes
    }

    public static function getEconomyProvider(): ?EconomyProvider {
        return self::$economyProvider;
    }

    public function onPlayerMove(PlayerMoveEvent $event): void {
        $player = $event->getPlayer();
        $this->setPlayerLastMoveTime($player->getName(), time());
    }

    public function onPlayerInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();
        $this->setPlayerLastMoveTime($player->getName(), time());
    }

    public function onBlockBreak(BlockBreakEvent $event): void {
        if ($this->config->getNested("declare_afk.when_breaking_block", true)) {
            $player = $event->getPlayer();
            $this->setPlayerLastMoveTime($player->getName(), time());
        }
    }

    public function onBlockPlace(BlockPlaceEvent $event): void {
        if ($this->config->getNested("declare_afk.when_placing_block", true)) {
            $player = $event->getPlayer();
            $this->setPlayerLastMoveTime($player->getName(), time());
        }
    }

    public function onEntityDamageByEntity(EntityDamageByEntityEvent $event): void {
        $damager = $event->getDamager();
        if ($damager instanceof Player && $this->config->getNested("declare_afk.when_dealing_damage", true)) {
            $this->setPlayerLastMoveTime($damager->getName(), time());
        }
    }

    public function onPlayerQuit(PlayerQuitEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        $this->removePlayerLastPosition($playerName);
    }

    public function checkAFKPlayers(): void {
        $afkInterval = $this->config->get("afk_check_interval", 300); // Get the AFK check interval
        foreach ($this->getServer()->getOnlinePlayers() as $player) {
            $playerName = $player->getName();
            $lastMoveTime = $this->getPlayerLastMoveTime($playerName);
            $currentTime = time();
            $afkDuration = $currentTime - $lastMoveTime;

            if ($afkDuration >= $afkInterval) { // Check if player has been AFK for the interval duration
                $afkLossPercentage = $this->config->get("afk_loss_percentage", 0.05);

                self::$economyProvider->getMoney($player, function (float $money) use ($player, $afkLossPercentage, $currentTime, $playerName) {
                    $afkLost = round($money * $afkLossPercentage, 2);

                    if ($afkLost > 0) {
                        self::$economyProvider->takeMoney($player, $afkLost, function (bool $success) use ($player, $afkLost, $currentTime, $playerName) {
                            if ($success) {
                                // Notify player of money loss
                                $afkLostMessage = str_replace("{AFK_LOST}", $afkLost, $this->config->get("afk_loss_message", "§cYou Lost Money by §e{AFK_LOST} §cBecause of AFK"));
                                $player->sendMessage($afkLostMessage);

                                // Update last move time
                                $this->setPlayerLastMoveTime($playerName, $currentTime);

                                // Send remaining money message
                                self::$economyProvider->getMoney($player, function (float $remainingMoney) use ($player) {
                                    $moneyMessage = str_replace("{Your_MONEY_LOST}", number_format($remainingMoney, 2), $this->config->get("money_message", "§aYour Remaining Money Is As Big As §e{Your_MONEY_LOST}."));
                                    $player->sendMessage($moneyMessage);
                                });
                            } else {
                                $player->sendMessage(TextFormat::RED . "There was an error while deducting your money or your money is already depleted.");
                                $this->getLogger()->warning("Failed to deduct player's money.");
                            }
                        });
                    }
                });
            }
        }
    }

    private function getPlayerLastPosition(string $playerName): ?Vector3 {
        return $this->afkPlayers[$playerName]["position"] ?? null;
    }

    private function setPlayerLastPosition(string $playerName, Vector3 $position): void {
        $this->afkPlayers[$playerName]["position"] = $position;
    }

    private function removePlayerLastPosition(string $playerName): void {
        unset($this->afkPlayers[$playerName]);
    }

    private function getPlayerLastMoveTime(string $playerName): int {
        return $this->afkPlayers[$playerName]["last_move_time"] ?? time();
    }

    private function setPlayerLastMoveTime(string $playerName, int $time): void {
        $this->afkPlayers[$playerName]["last_move_time"] = $time;
    }
}
