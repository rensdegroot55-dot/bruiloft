<?php
require_once __DIR__ . '/config.php';

function get_db(): PDO {
    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA journal_mode=WAL');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS items (
            id          TEXT PRIMARY KEY,
            phase_id    TEXT NOT NULL,
            sort_order  INTEGER NOT NULL DEFAULT 0,
            time_start  TEXT,
            time_end    TEXT,
            who         TEXT NOT NULL,
            what        TEXT NOT NULL,
            location    TEXT NOT NULL DEFAULT '',
            is_secret   INTEGER NOT NULL DEFAULT 0,
            date        TEXT NOT NULL DEFAULT '2026-08-09',
            created_at  TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS phases (
            id          TEXT PRIMARY KEY,
            label       TEXT NOT NULL,
            emoji       TEXT NOT NULL DEFAULT '',
            date        TEXT NOT NULL DEFAULT '2026-08-09',
            sort_order  INTEGER NOT NULL DEFAULT 0
        );

        CREATE TABLE IF NOT EXISTS state (
            item_id     TEXT PRIMARY KEY,
            is_done     INTEGER NOT NULL DEFAULT 0,
            note        TEXT NOT NULL DEFAULT '',
            updated_at  TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS corsages (
            id          TEXT PRIMARY KEY,
            name        TEXT NOT NULL,
            role        TEXT NOT NULL,
            is_done     INTEGER NOT NULL DEFAULT 0,
            sort_order  INTEGER NOT NULL DEFAULT 0
        );

        CREATE TABLE IF NOT EXISTS changelog (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            action      TEXT NOT NULL,
            payload     TEXT NOT NULL DEFAULT '',
            created_at  TEXT NOT NULL DEFAULT (datetime('now'))
        );
    ");

    // Seed initial data if empty
    $count = $pdo->query("SELECT COUNT(*) FROM phases")->fetchColumn();
    if ($count == 0) {
        seed_data($pdo);
    }

    return $pdo;
}

function log_change(PDO $pdo, string $action, array $payload = []): void {
    $pdo->prepare("INSERT INTO changelog (action, payload) VALUES (?, ?)")
        ->execute([$action, json_encode($payload)]);
}

function seed_data(PDO $pdo): void {
    $phases = [
        ['vrijdag',   '🌙', 'Vrijdag avond',  '2026-08-08', 0],
        ['ochtend',   '☀️', 'Ochtend',         '2026-08-09', 1],
        ['fotoshoot', '📸', 'Fotoshoot',        '2026-08-09', 2],
        ['opbouw',    '🏡', 'Opbouw',           '2026-08-09', 3],
        ['programma', '💒', 'Programma',        '2026-08-09', 4],
    ];
    $sp = $pdo->prepare("INSERT INTO phases (id,emoji,label,date,sort_order) VALUES (?,?,?,?,?)");
    foreach ($phases as $p) $sp->execute($p);

    $items = [
        // VRIJDAG
        ['v1','vrijdag',0,null,null,'Danique, Sarah & Laura','Overnachting kamer 1','Shanghai Hotel, Delft',0,'2026-08-08'],
        ['v2','vrijdag',1,null,null,'Rens, Niels & Melvin','Overnachting kamer 2','Shanghai Hotel, Delft',0,'2026-08-08'],
        // OCHTEND
        ['o1','ochtend',0,'07:30',null,'Mirjam (MUA 1)','Aankomst & opbouw hotelkamer','Shanghai Hotel, Delft',0,'2026-08-09'],
        ['o2','ochtend',1,'07:30',null,'Lianne (MUA 2)','Aankomst hotel','Shanghai Hotel, Delft',0,'2026-08-09'],
        ['o3','ochtend',2,'07:45','08:00','Lianne (MUA 2)','Opbouw in hotelkamer','Shanghai Hotel, Delft',0,'2026-08-09'],
        ['o4','ochtend',3,'07:45','09:00','Laura (schoonzus)','Haar & make-up door Mirjam','Shanghai Hotel, Delft',0,'2026-08-09'],
        ['o5','ochtend',4,'08:00',null,'Kelly (fotograaf 1)','Aankomst hotel','Shanghai Hotel, Delft',0,'2026-08-09'],
        ['o6','ochtend',5,'08:00',null,'Jordi (fotograaf 2)','Aankomst hotel','Shanghai Hotel, Delft',0,'2026-08-09'],
        ['o7','ochtend',6,'08:00','10:30','Danique (bruid)','Haar & make-up door Lianne','Shanghai Hotel, Delft',0,'2026-08-09'],
        ['o8','ochtend',7,'09:15','10:30','Carmen & Yvo','Haar & make-up Mirjam · Yvo arriveert','Shanghai Hotel, Delft',0,'2026-08-09'],
        ['o9','ochtend',8,'10:30',null,'Carmen & Yvo','Vertrek naar huis (na make-up & foto\'s)','Shanghai Hotel, Delft',0,'2026-08-09'],
        ['o10','ochtend',9,'10:30','11:15','Wendy (moeder bruid)','Haar & make-up door Lianne','Shanghai Hotel, Delft',0,'2026-08-09'],
        ['o11','ochtend',10,'11:15',null,'Wendy (moeder bruid)','Vertrek naar huis','Shanghai Hotel, Delft',0,'2026-08-09'],
        ['o12','ochtend',11,'10:45','12:00','Daniëlle (ceremoniemeester)','Haar & make-up door Mirjam','Shanghai Hotel, Delft',0,'2026-08-09'],
        ['o13','ochtend',12,'11:15','12:00','Sarah (bruidsmeisje 1)','Haar & make-up door Lianne','Shanghai Hotel, Delft',0,'2026-08-09'],
        ['o14','ochtend',13,'12:00','12:15','Mirjam (MUA 1)','Afbouwen & vertrek','Shanghai Hotel, Delft',0,'2026-08-09'],
        ['o15','ochtend',14,'12:00','12:15','Lianne (MUA 2)','Afbouwen & vertrek','Shanghai Hotel, Delft',0,'2026-08-09'],
        ['o16','ochtend',15,null,null,'Marcus (vader bruid)','Trouwauto afleveren — tijdstip NTB (vóór 12:00)','Shanghai Hotel, Delft',0,'2026-08-09'],
        ['o17','ochtend',16,null,null,'Het Bloemenhart (bloemist)','Aflevering boeket & corsages — tijdstip NTB','Shanghai Hotel, Delft',0,'2026-08-09'],
        ['o18','ochtend',17,'12:00',null,'Rens & Jordi','Vertrek hotel → fotoshootlocatie (trouwauto)','Shanghai Hotel, Delft',0,'2026-08-09'],
        ['o19','ochtend',18,'12:15',null,'Danique & Kelly','Vertrek hotel → fotoshootlocatie (apart)','Shanghai Hotel, Delft',0,'2026-08-09'],
        // FOTOSHOOT
        ['f1','fotoshoot',0,'12:30',null,'Rens, Danique, Kelly & Jordi','Aankomst fotoshootlocatie','Locatie nader te bepalen',0,'2026-08-09'],
        ['f2','fotoshoot',1,'12:30',null,'Sascha & Billie','Aankomst bij fotoshootlocatie','Locatie nader te bepalen',0,'2026-08-09'],
        ['f3','fotoshoot',2,'12:30','13:30','Rens, Danique & Billie','Familiefotoshoot','Locatie nader te bepalen',0,'2026-08-09'],
        ['f4','fotoshoot',3,'13:30',null,'Sascha & Billie','Vertrek → Hoeve Zzamen','Hoeve Zzamen, Zoetermeer',0,'2026-08-09'],
        ['f5','fotoshoot',4,'13:30',null,'Rens & Danique','Uitspreken geloftes (privé) 💕','Locatie nader te bepalen',0,'2026-08-09'],
        ['f6','fotoshoot',5,'14:00',null,'Rens, Danique & fotografen','Vertrek → Hoeve Zzamen (trouwauto)','Hoeve Zzamen, Zoetermeer',0,'2026-08-09'],
        // OPBOUW
        ['op1','opbouw',0,'13:00',null,'Daniëlle, Melvin, Laura, Niels & Sarah','Aankomst voor opbouw','Hoeve Zzamen, Zoetermeer',0,'2026-08-09'],
        ['op2','opbouw',1,null,null,'Feestopbouwbedrijf (naam NTB)','Aankomst & opbouw feest — tijdstip NTB','Hoeve Zzamen, Zoetermeer',0,'2026-08-09'],
        ['op3','opbouw',2,'14:00','14:30','Rens & Danique','Aankomst Hoeve Zzamen','Hoeve Zzamen, Zoetermeer',0,'2026-08-09'],
        ['op4','opbouw',3,'14:30',null,'Marcus (vader bruid)','Aankomst melkhuisje — firstlook','Melkhuisje, Hoeve Zzamen',0,'2026-08-09'],
        ['op5','opbouw',4,'14:30','15:00','Sulaika (BABS)','Aankomst trouwlocatie','Hoeve Zzamen, Zoetermeer',0,'2026-08-09'],
        // PROGRAMMA
        ['p1','programma',0,'15:00','15:30','Alle gasten','Inloop gasten','Hoeve Zzamen',0,'2026-08-09'],
        ['p2','programma',1,'15:30','16:00','Alle aanwezigen','Huwelijksceremonie · voltrokken door Sulaika (BABS)','Hoeve Zzamen',0,'2026-08-09'],
        ['p3','programma',2,'16:00',null,'Sulaika (BABS)','Toost mee tijdens borrel, daarna vertrek','Hoeve Zzamen',0,'2026-08-09'],
        ['p4','programma',3,'16:00','18:00','Alle gasten','Borrel · 2 momenten speeches/surprises','Hoeve Zzamen',0,'2026-08-09'],
        ['p5','programma',4,'18:00',null,'Billie & gast Lida','Billie vertrekt met Lida naar huis','Hoeve Zzamen',0,'2026-08-09'],
        ['p6','programma',5,'18:00',null,'Rens & Danique','Openingsspeech bruidspaar','Hoeve Zzamen',0,'2026-08-09'],
        ['p7','programma',6,'18:00','20:00','Alle gasten','Avondeten (BBQ) · 3 momenten speeches tijdens diner','Hoeve Zzamen',0,'2026-08-09'],
        ['p8','programma',7,'19:30',null,'Brassband','Heimelijke aankomst via melkhuisje','Melkhuisje, Hoeve Zzamen',1,'2026-08-09'],
        ['p9','programma',8,'19:30',null,'Thomas (DJ) & Sjoerd (saxofonist)','Aankomst & opbouw','Hoeve Zzamen',0,'2026-08-09'],
        ['p10','programma',9,'20:00','00:00','Alle gasten','Feest! 🎉','Hoeve Zzamen',0,'2026-08-09'],
        ['p11','programma',10,'20:15',null,'Brassband + Rens & Danique','Brassband komt op met bruidspaar','Hoeve Zzamen',1,'2026-08-09'],
        ['p12','programma',11,null,null,'Gasten','1 moment voor speeches/surprises tijdens feest','Hoeve Zzamen',0,'2026-08-09'],
        ['p13','programma',12,'00:00',null,'Alle gasten','Einde feest — afsluiting nader te bepalen','Hoeve Zzamen',0,'2026-08-09'],
    ];

    $si = $pdo->prepare("INSERT INTO items
        (id,phase_id,sort_order,time_start,time_end,who,what,location,is_secret,date)
        VALUES (?,?,?,?,?,?,?,?,?,?)");
    foreach ($items as $i) $si->execute($i);

    $corsages = [
        ['c1','Rens','Bruidegom',0],
        ['c2','Yvo','Getuige bruidegom 1',1],
        ['c3','Niels','Beste vriend bruidegom',2],
        ['c4','Melvin','Getuige bruidegom 2',3],
        ['c5','Sarah','Bruidsmeisje 1',4],
        ['c6','Carmen','Bruidsmeisje 2',5],
        ['c7','Opa Ton','Getuige bruid 1',6],
        ['c8','Oma Willie','Getuige bruid 2',7],
        ['c9','Billie','Dochter bruidspaar',8],
        ['c10','Sascha','Zus van Rens',9],
        ['c11','Daniëlle','Ceremoniemeester',10],
    ];
    $sc = $pdo->prepare("INSERT INTO corsages (id,name,role,is_done,sort_order) VALUES (?,?,?,0,?)");
    foreach ($corsages as $c) $sc->execute($c);
}
