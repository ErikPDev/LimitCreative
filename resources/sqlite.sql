-- #! sqlite
-- #{ limitcreative
-- #    { init
CREATE TABLE IF NOT EXISTS "LimitCreative" (
    "UUID"	TEXT UNIQUE,
    "creative"	TEXT DEFAULT "",
    "survival"	TEXT DEFAULT "",
    "adventure" TEXT DEFAULT "",
    "spectator" TEXT DEFAULT "",
    PRIMARY KEY("UUID")
);
-- #    }
-- #    { setCreativeInventory
-- #    :inventory string
-- #    :UUID string
INSERT OR REPLACE INTO "LimitCreative" ("UUID", "creative", "survival", "adventure", "spectator") VALUES (:UUID, :inventory, ifnull((select survival from LimitCreative where UUID = :UUID), ""), ifnull((select adventure from LimitCreative where UUID = :UUID), ""), ifnull((select spectator from LimitCreative where UUID = :UUID), ""));
-- #    }
-- #    { getCreativeInventory
-- # 	:UUID string
SELECT creative FROM LimitCreative WHERE UUID = :UUID;
-- #    }
-- #    { setSurvivalInventory
-- #    :inventory string
-- #    :UUID string
INSERT OR REPLACE INTO "LimitCreative" ("UUID", "creative", "survival", "adventure", "spectator") VALUES (:UUID, ifnull((select creative from LimitCreative where UUID = :UUID), ""), :inventory, ifnull((select adventure from LimitCreative where UUID = :UUID), ""), ifnull((select spectator from LimitCreative where UUID = :UUID), ""));
-- #    }
-- #    { getSurvivalInventory
-- # 	:UUID string
SELECT survival FROM LimitCreative WHERE UUID = :UUID;
-- #    }
-- #    { setAdventureInventory
-- #    :inventory string
-- #    :UUID string
INSERT OR REPLACE INTO "LimitCreative" ("UUID", "creative", "survival", "adventure", "spectator") VALUES (:UUID, ifnull((select creative from LimitCreative where UUID = :UUID), ""), ifnull((select adventure from LimitCreative where UUID = :UUID), ""), :inventory, ifnull((select spectator from LimitCreative where UUID = :UUID), ""));
-- #    }
-- #    { getAdventureInventory
-- # 	:UUID string
SELECT adventure FROM LimitCreative WHERE UUID = :UUID;
-- #    }
-- #    { setSpectatorInventory
-- #    :inventory string
-- #    :UUID string
INSERT OR REPLACE INTO "LimitCreative" ("UUID", "creative", "survival", "adventure", "spectator") VALUES (:UUID, ifnull((select creative from LimitCreative where UUID = :UUID), ""), ifnull((select adventure from LimitCreative where UUID = :UUID), ""), ifnull((select spectator from LimitCreative where UUID = :UUID), ""), :inventory);
-- #    }
-- #    { getSpectatorInventory
-- # 	:UUID string
SELECT spectator FROM LimitCreative WHERE UUID = :UUID;
-- #    }
-- #    { clearInventories
-- # 	:UUID string
INSERT OR REPLACE INTO "LimitCreative" ("UUID", "creative", "survival", "adventure", "spectator") VALUES (:UUID, "", "", "", "");
-- #    }
-- #}