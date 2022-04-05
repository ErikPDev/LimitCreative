<h1 align="center">LimitCreative</h1>
You can officially limit the Creative Gamemode using this plugin!

Features:

- [x] Clear Inventory on PlayerDeath
- [x] Separate inventories per gamemode.
- [x] Forbidden Creative Blocks, this blocks Interactions, and placements.
- [x] Blocks Middle Click to prevent duplication
- [x] Blocks PVP/Entity Damage
- [x] Blocks picking up items
- [x] Blocks dropping items
- [x] Permission to bypass this plugin.

# Permission(s)

| Permission name      | Description                     | Default |
|----------------------|---------------------------------|---------|
| limitcreative.bypass | Bypass the LimitCreative plugin | op      |


# Default Config

```yaml
# Welcome to Limit Creative for PM4!

clearInventory: true # Set this to true if you want to clear the player inventory before death or switching gamemodes.
separateInventories: true # Set this to true if you want each game mode to have a separate inventory

allowDropItems: false # Set this to true if you allow the player to drop items in Creative.
allowPickupItems: false # Set this to true if you allow the player to pick up items in Creative.

blacklisted-blocks:
  - "ender_chest"
  - "chest"
  - "furnace"
  - "burning_furnace"
  - "trapped_chest"
  - "enchantment_table"
  - "anvil"
  - "item_frame_block"
  - "shulker_box"
  - "undyed_shulker_box"

```