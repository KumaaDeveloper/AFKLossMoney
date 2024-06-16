# General
AFKLossMoney is a Pocketmine plug-in that functions to reduce player money when the player AFK

## Features
- Reducing your money when you AFK
- Reducing your money using percentages
- Custom economy
  
## Configuration
```yaml
# Configuration for AFKLossMoney plugin

# Interval (in seconds) to check for AFK players
afk_check_interval: 10 # Default is 5 minutes

# Percentage of money lost due to being AFK
afk_loss_percentage: 0.05 # Default is 5%

# The message sent to players when they lose money due to being AFK
afk_loss_message: "§cYou Lost Money by §e{AFK_LOST} §cBecause of AFK"

# The message sent to players to inform them of their remaining money
money_message: "§aYour remaining money is §e{Your_MONEY_LOST}."

# Economy provider settings (customize based on your economy settings)
economy:
  type: "economyapi" # Change this to your specific economy provider if needed (bedrockeconomy/economyapi)

# AFK declaration settings
declare_afk:
  when_breaking_block: true
  when_placing_block: true
  when_dealing_damage: true
```

### ✔ Credits & Depend
| Authors | Github | Lib |
|---------|--------|-----|
| Cooldogepm | [Cooldogepm](https://github.com/cooldogepm) | [BedrockEconomy](https://github.com/cooldogepm/BedrockEconomy) |
| Mathchat900 | [Mathchat900](https://github.com/mathchat900) | [EconomyAPI](https://github.com/mathchat900/EconomyAPI-PM5) |
| DaPigGuy | [DaPigGuy](https://github.com/DaPigGuy) | [libPiggyEconomy](https://github.com/DaPigGuy/libPiggyEconomy) |
