<?php
require_once __DIR__ . '/db.php';
$pdo = get_db();

$phases   = $pdo->query("SELECT * FROM phases ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
$items    = $pdo->query(
    "SELECT i.*, COALESCE(s.is_done,0) as is_done, COALESCE(s.note,'') as note
     FROM items i LEFT JOIN state s ON s.item_id=i.id
     ORDER BY i.phase_id, i.sort_order"
)->fetchAll(PDO::FETCH_ASSOC);
$corsages = $pdo->query("SELECT * FROM corsages ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
$lastId   = $pdo->query("SELECT COALESCE(MAX(id),0) FROM changelog")->fetchColumn();

$initData = json_encode([
    'phases'         => $phases,
    'items'          => $items,
    'corsages'       => $corsages,
    'last_change_id' => (int)$lastId,
]);
?><!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no"/>
<meta name="apple-mobile-web-app-capable" content="yes"/>
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"/>
<title>💍 Danique & Rens</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,600;1,400&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<style>
:root {
  --rose:#c8737a;    --rose-pale:#fdf5f5;   --rose-light:#f2dada;
  --sage:#7a9e7e;    --sage-pale:#f4f9f4;   --sage-light:#d8ead9;
  --gold:#c9a96e;    --gold-pale:#fdf8ef;   --gold-light:#f0e0c0;
  --purple:#9b59b6;  --purple-pale:#fbf5ff;
  --ink:#2d2420;     --ink-soft:#7a6258;    --ink-faint:#b8a89e;
  --surface:#fffcf9; --bg:#f2e8dc;
  --border:rgba(201,169,110,0.18);
  --sh:0 2px 12px rgba(45,36,32,.08);
  --sh-lg:0 8px 32px rgba(45,36,32,.14);
  --r:14px; --r-lg:20px;
  --nav-h:70px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
html{height:100%;overflow:hidden}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--ink);
  height:100%;overflow:hidden;display:flex;flex-direction:column}

/* ── HEADER ── */
.hero{
  background:linear-gradient(160deg,#2d2420,#4a3020 60%,#3d2818);
  padding:env(safe-area-inset-top,0) 0 0;
  flex-shrink:0;
  box-shadow:0 4px 24px rgba(0,0,0,.3);
  position:relative;z-index:100;
}
.hero-inner{padding:16px 18px 14px}
.hero-top{display:flex;justify-content:space-between;align-items:flex-start}
.hero-left h1{font-family:'Cormorant Garamond',serif;font-size:1.75rem;color:#f5ede0;line-height:1}
.hero-left h1 em{color:var(--gold);font-style:italic}
.hero-left .sub{font-size:.65rem;color:rgba(245,237,224,.5);margin-top:4px;
  letter-spacing:.14em;text-transform:uppercase}
.hero-right{text-align:right}
#liveClock{font-family:'Cormorant Garamond',serif;font-size:2.2rem;
  color:var(--gold);font-variant-numeric:tabular-nums;line-height:1}
.clock-sub{font-size:.6rem;color:rgba(245,237,224,.35);letter-spacing:.1em;
  text-transform:uppercase;margin-top:2px;display:flex;align-items:center;justify-content:flex-end;gap:4px}
.sync-dot{width:6px;height:6px;border-radius:50%;background:#4a7c59;
  transition:background .4s;flex-shrink:0}
.sync-dot.err{background:var(--rose);animation:blink 1.4s infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}

/* progress */
.hero-progress{padding:0 18px 14px;display:flex;align-items:center;gap:10px}
.prog-track{flex:1;height:3px;background:rgba(255,255,255,.12);border-radius:3px;overflow:hidden}
.prog-fill{height:100%;background:linear-gradient(90deg,var(--rose),var(--gold));
  border-radius:3px;transition:width .6s cubic-bezier(.4,0,.2,1)}
.prog-label{font-size:.68rem;color:rgba(245,237,224,.45);font-variant-numeric:tabular-nums;
  font-weight:600;white-space:nowrap}

/* ── PHASE TABS (pill strip) ── */
.phase-strip{
  display:flex;overflow-x:auto;gap:6px;padding:10px 16px;
  background:rgba(255,252,249,.97);border-bottom:1px solid var(--border);
  -webkit-overflow-scrolling:touch;scrollbar-width:none;flex-shrink:0;
}
.phase-strip::-webkit-scrollbar{display:none}
.phase-pill{
  flex-shrink:0;display:flex;align-items:center;gap:5px;
  padding:6px 13px;border-radius:30px;border:1.5px solid var(--border);
  background:transparent;color:var(--ink-soft);font-size:.75rem;font-weight:600;
  font-family:'Inter',sans-serif;cursor:pointer;transition:all .2s ease;white-space:nowrap;
}
.phase-pill.active{background:var(--ink);color:#f5ede0;border-color:var(--ink)}
.phase-pill .pc{font-size:.62rem;padding:1px 6px;border-radius:10px;
  background:var(--gold-light);color:#7a5a20}
.phase-pill.active .pc{background:rgba(255,255,255,.2);color:#f5ede0}
.phase-pill.has-alert .pc{background:var(--rose-light);color:var(--rose)}

/* ── SWIPE CONTAINER ── */
.swipe-host{
  flex:1;overflow:hidden;position:relative;
}
.swipe-track{
  display:flex;height:100%;
  transition:transform .32s cubic-bezier(.4,0,.2,1);
  will-change:transform;
}
.swipe-panel{
  flex-shrink:0;width:100%;height:100%;
  overflow-y:auto;-webkit-overflow-scrolling:touch;
  padding:12px 14px 20px;
}
/* hide scrollbar but keep scroll */
.swipe-panel::-webkit-scrollbar{display:none}
.swipe-panel{scrollbar-width:none}

/* ── NEXT-UP BANNER ── */
.next-banner{
  background:linear-gradient(100deg,var(--rose-pale),var(--gold-pale));
  border:1px solid var(--gold-light);border-radius:var(--r);
  padding:10px 13px;margin-bottom:11px;
  display:flex;align-items:center;gap:10px;
}
.nb-icon{font-size:1.15rem;flex-shrink:0}
.nb-body{flex:1;min-width:0}
.nb-lbl{font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--ink-faint)}
.nb-what{font-size:.84rem;font-weight:600;color:var(--ink);
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.nb-cd{font-size:.75rem;font-weight:700;color:var(--rose);white-space:nowrap;flex-shrink:0}

/* ── SECTION LABEL ── */
.sec-label{font-family:'Cormorant Garamond',serif;font-size:1.05rem;
  color:var(--ink-soft);margin:2px 2px 10px}

/* ── ITEM CARD ── */
.item-card{
  background:var(--surface);border-radius:var(--r);
  box-shadow:var(--sh);margin-bottom:9px;overflow:hidden;
  border:1px solid var(--border);border-left:4px solid var(--gold);
  transition:transform .12s,box-shadow .12s,opacity .25s;
}
.item-card:active{transform:scale(.984);box-shadow:none}
.item-card.is-done{opacity:.62;border-left-color:var(--sage);background:var(--sage-pale)}
.item-card.is-late{border-left-color:var(--rose);background:#fff5f5}
.item-card.is-soon{border-left-color:#d4a017;background:#fffbf0}
.item-card.is-secret{border-left-color:var(--purple);background:var(--purple-pale)}

.item-row{display:flex;align-items:center;padding:11px 12px 9px 13px;gap:10px;
  cursor:pointer;user-select:none}

/* check circle */
.chk{
  flex-shrink:0;width:28px;height:28px;border-radius:50%;
  border:2px solid var(--gold);cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  position:relative;overflow:hidden;background:transparent;
  transition:border-color .2s;
}
.chk::after{content:'';position:absolute;inset:0;border-radius:50%;
  background:var(--sage);transform:scale(0);
  transition:transform .25s cubic-bezier(.34,1.56,.64,1)}
.chk.on{border-color:var(--sage)}
.chk.on::after{transform:scale(1)}
.chk-icon{position:relative;z-index:1;color:#fff;font-size:.8rem;
  opacity:0;transform:scale(0);transition:all .18s .06s}
.chk.on .chk-icon{opacity:1;transform:scale(1)}
.item-card.is-late .chk{border-color:var(--rose)}
.item-card.is-soon .chk{border-color:#d4a017}
.item-card.is-secret .chk{border-color:var(--purple)}

.item-body{flex:1;min-width:0}
.item-time-row{display:flex;align-items:center;gap:5px;margin-bottom:2px}
.itime{font-size:.68rem;font-weight:700;letter-spacing:.05em;color:var(--gold)}
.item-card.is-done .itime{color:var(--sage)}
.item-card.is-late .itime{color:var(--rose)}
.item-card.is-soon .itime{color:#b8880a}
.badge-s{font-size:.58rem;font-weight:700;letter-spacing:.05em;
  background:var(--purple);color:#fff;border-radius:5px;padding:1px 5px;text-transform:uppercase}
.iwhat{font-size:.88rem;font-weight:600;color:var(--ink);line-height:1.3}
.item-card.is-done .iwhat{text-decoration:line-through;color:var(--ink-faint)}
.iwho{font-size:.7rem;color:var(--ink-soft);margin-top:2px}
.iloc{font-size:.66rem;color:var(--ink-faint);margin-top:1px}

/* countdown chip */
.cd{flex-shrink:0;text-align:center;min-width:46px}
.cd-n{font-size:.95rem;font-weight:700;font-variant-numeric:tabular-nums;line-height:1.1}
.cd-u{font-size:.56rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;opacity:.7}
.cd.cf{color:var(--ink-faint)}.cd.cs{color:#b8880a}.cd.cl{color:var(--rose)}.cd.ck{color:var(--sage)}

/* notes expand */
.item-notes{max-height:0;overflow:hidden;transition:max-height .3s cubic-bezier(.4,0,.2,1)}
.item-notes.open{max-height:160px}
.notes-inner{padding:0 13px 11px 53px;border-top:1px solid var(--border)}
.notes-lbl{font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;
  color:var(--ink-faint);margin:8px 0 4px}
textarea.note{width:100%;padding:8px 10px;font-family:'Inter',sans-serif;font-size:.8rem;
  color:var(--ink);background:var(--gold-pale);border:1.5px solid var(--gold-light);
  border-radius:8px;resize:none;min-height:56px;line-height:1.5;
  transition:border-color .2s}
textarea.note:focus{outline:none;border-color:var(--gold)}

/* ── NON-DRAAIBOEK VIEWS ── */
.static-view{height:100%;overflow-y:auto;padding:14px 14px 20px;scrollbar-width:none}
.static-view::-webkit-scrollbar{display:none}

/* contacts */
.c-card{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);
  box-shadow:var(--sh);padding:12px 13px;display:flex;align-items:center;gap:11px;margin-bottom:9px}
.c-av{width:40px;height:40px;border-radius:50%;flex-shrink:0;font-size:.88rem;font-weight:700;
  color:#fff;display:flex;align-items:center;justify-content:center;
  background:linear-gradient(135deg,var(--gold),var(--rose))}
.c-name{font-size:.88rem;font-weight:700}
.c-role{font-size:.7rem;color:var(--rose);font-weight:600;margin-top:1px}
.c-note{font-size:.66rem;color:var(--ink-faint);margin-top:1px}

/* corsages */
.cor-intro{background:var(--gold-pale);border:1px solid var(--gold-light);
  border-radius:var(--r);padding:11px 13px;font-size:.78rem;color:var(--ink-soft);
  margin-bottom:12px;line-height:1.5}
.cor-card{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);
  box-shadow:var(--sh);padding:11px 13px;display:flex;align-items:center;
  justify-content:space-between;margin-bottom:8px;transition:background .2s}
.cor-card.done{background:var(--sage-pale);border-color:var(--sage-light)}
.cor-name{font-size:.88rem;font-weight:700}
.cor-role{font-size:.7rem;color:var(--ink-soft);margin-top:1px}
.cor-btn{width:30px;height:30px;border-radius:50%;border:2px solid var(--gold);
  background:transparent;cursor:pointer;display:flex;align-items:center;justify-content:center;
  position:relative;overflow:hidden;transition:border-color .2s}
.cor-btn::after{content:'';position:absolute;inset:0;border-radius:50%;background:var(--sage);
  transform:scale(0);transition:transform .22s cubic-bezier(.34,1.56,.64,1)}
.cor-btn.done{border-color:var(--sage)}
.cor-btn.done::after{transform:scale(1)}
.cor-btn-ic{position:relative;z-index:1;color:#fff;font-size:.8rem;
  opacity:0;transform:scale(0);transition:all .18s .05s}
.cor-btn.done .cor-btn-ic{opacity:1;transform:scale(1)}

/* open punten */
.punt{background:var(--surface);border-radius:var(--r);border:1px solid #f5d0a0;
  border-left:4px solid #d4a017;box-shadow:var(--sh);padding:11px 13px;
  margin-bottom:8px;font-size:.82rem;line-height:1.5}

/* ── BOTTOM NAV ── */
.bnav{
  flex-shrink:0;
  display:flex;justify-content:space-around;
  background:rgba(255,252,249,.97);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);
  border-top:1px solid var(--border);box-shadow:0 -3px 16px rgba(45,36,32,.07);
  padding:8px 0 max(10px,env(safe-area-inset-bottom));
  position:relative;z-index:100;
}
.nb{display:flex;flex-direction:column;align-items:center;gap:2px;
  background:none;border:none;cursor:pointer;color:var(--ink-faint);
  font-size:.58rem;font-weight:600;font-family:'Inter',sans-serif;
  text-transform:uppercase;letter-spacing:.06em;padding:3px 12px;transition:color .2s;
  position:relative}
.nb.active{color:var(--ink)}
.nb .ni{font-size:1.35rem;line-height:1;transition:transform .2s cubic-bezier(.34,1.56,.64,1)}
.nb.active .ni{transform:scale(1.18)}
/* active indicator dot */
.nb::after{content:'';position:absolute;bottom:-8px;left:50%;transform:translateX(-50%);
  width:4px;height:4px;border-radius:50%;background:var(--gold);
  opacity:0;transition:opacity .2s}
.nb.active::after{opacity:1}

/* ── SLIDE-IN SHEET (notes) ── */
.sheet-bg{position:fixed;inset:0;background:rgba(45,36,32,.45);
  z-index:400;opacity:0;pointer-events:none;transition:opacity .25s;backdrop-filter:blur(2px)}
.sheet-bg.open{opacity:1;pointer-events:all}
.sheet{position:fixed;bottom:0;left:0;right:0;
  background:var(--surface);border-radius:20px 20px 0 0;
  padding:0 18px max(24px,env(safe-area-inset-bottom));
  z-index:401;transform:translateY(100%);transition:transform .3s cubic-bezier(.4,0,.2,1);
  box-shadow:0 -8px 40px rgba(45,36,32,.18)}
.sheet.open{transform:translateY(0)}
.sheet-handle{width:36px;height:4px;background:var(--border);border-radius:4px;
  margin:12px auto 16px}
.sheet-title{font-family:'Cormorant Garamond',serif;font-size:1.1rem;
  color:var(--ink);margin-bottom:12px}
textarea.sheet-note{width:100%;padding:11px 13px;font-family:'Inter',sans-serif;
  font-size:.88rem;color:var(--ink);background:var(--gold-pale);
  border:1.5px solid var(--gold-light);border-radius:10px;
  resize:none;min-height:100px;line-height:1.6;transition:border-color .2s}
textarea.sheet-note:focus{outline:none;border-color:var(--gold)}
.sheet-save{width:100%;margin-top:10px;padding:12px;border-radius:10px;
  border:none;background:var(--ink);color:#f5ede0;font-family:'Inter',sans-serif;
  font-weight:700;font-size:.88rem;cursor:pointer;transition:opacity .2s}
.sheet-save:active{opacity:.8}

/* ── CONFETTI ── */
.cf{position:fixed;pointer-events:none;z-index:9999;
  animation:cffall 1.1s ease-out forwards;border-radius:2px}
@keyframes cffall{0%{transform:translateY(0) rotate(0) scale(1);opacity:1}
  100%{transform:translateY(180px) rotate(540deg) scale(.4);opacity:0}}

/* ── TOAST ── */
.toast{position:fixed;bottom:calc(var(--nav-h) + 8px);left:50%;
  transform:translateX(-50%) translateY(20px);opacity:0;
  background:var(--ink);color:#f5ede0;padding:8px 18px;
  border-radius:30px;font-size:.78rem;font-weight:600;
  transition:all .28s cubic-bezier(.4,0,.2,1);z-index:500;
  white-space:nowrap;pointer-events:none;box-shadow:var(--sh-lg)}
.toast.show{transform:translateX(-50%) translateY(0);opacity:1}

/* ── SLIDE ANIM ── */
@keyframes slideIn{from{opacity:0;transform:translateX(18px)}to{opacity:1;transform:none}}
.slide-in{animation:slideIn .22s ease both}
</style>
</head>
<body>

<!-- HERO -->
<header class="hero">
<div class="hero-inner">
  <div class="hero-top">
    <div class="hero-left">
      <h1>Danique <em>&</em> Rens</h1>
      <div class="sub">9 augustus 2026 · Hoeve Zzamen</div>
    </div>
    <div class="hero-right">
      <div id="liveClock">--:--</div>
      <div class="clock-sub">Live <span class="sync-dot" id="syncDot"></span></div>
    </div>
  </div>
</div>
<div class="hero-progress">
  <div class="prog-track"><div class="prog-fill" id="progFill" style="width:0%"></div></div>
  <div class="prog-label" id="progLabel">0 / 0</div>
</div>
</header>

<!-- PHASE TABS -->
<div class="phase-strip" id="phaseStrip"></div>

<!-- SWIPE HOST (draaiboek panels) -->
<div class="swipe-host" id="swipeHost">
  <div class="swipe-track" id="swipeTrack"></div>
</div>

<!-- CONTACTEN -->
<div class="static-view" id="viewContacten" style="display:none">
  <div style="font-family:'Cormorant Garamond',serif;font-size:1.1rem;color:var(--ink-soft);margin-bottom:12px">Leveranciers & contacten</div>
  <div id="contactList"></div>
</div>

<!-- CORSAGES -->
<div class="static-view" id="viewCorsages" style="display:none">
  <div style="font-family:'Cormorant Garamond',serif;font-size:1.1rem;color:var(--ink-soft);margin-bottom:10px">Corsages & boeket</div>
  <div class="cor-intro">🌸 Geleverd door <strong>Het Bloemenhart</strong> (Berkel en Rodenrijs).<br>Vink af zodra de corsage is overhandigd.</div>
  <div id="corsageList"></div>
</div>

<!-- OPEN PUNTEN -->
<div class="static-view" id="viewPunten" style="display:none">
  <div style="font-family:'Cormorant Garamond',serif;font-size:1.1rem;color:var(--ink-soft);margin-bottom:12px">Openstaande punten</div>
  <?php $punten=[
    'Locatie fotoshoot & geloftes: nader te bepalen',
    'Tijdstip aflevering trouwauto door Marcus (moet vóór 12:00 aanwezig zijn)',
    'Tijdstip aflevering bruidsboeket & corsages door Het Bloemenhart',
    'Naam feestopbouwbedrijf + aankomsttijd: nader te bepalen',
    'Eindmoment feest (last dance / uitzwaai): nader te bepalen',
    'Verantwoordelijkheden per moment: nader in te vullen',
  ];
  foreach($punten as $p) echo '<div class="punt">⚠️ '.htmlspecialchars($p).'</div>'; ?>
</div>

<!-- BOTTOM NAV -->
<nav class="bnav">
  <button class="nb active" id="nav-draaiboek" onclick="showView('draaiboek')">
    <span class="ni">📋</span>Draaiboek
  </button>
  <button class="nb" id="nav-contacten" onclick="showView('contacten')">
    <span class="ni">👥</span>Contacten
  </button>
  <button class="nb" id="nav-corsages" onclick="showView('corsages')">
    <span class="ni">🌸</span>Corsages
  </button>
  <button class="nb" id="nav-punten" onclick="showView('punten')">
    <span class="ni">⚠️</span>Openstaand
  </button>
</nav>

<!-- NOTES SHEET -->
<div class="sheet-bg" id="sheetBg" onclick="closeSheet()"></div>
<div class="sheet" id="sheet">
  <div class="sheet-handle"></div>
  <div class="sheet-title" id="sheetTitle">Notitie</div>
  <textarea class="sheet-note" id="sheetNote" placeholder="Voeg een notitie toe…"></textarea>
  <button class="sheet-save" onclick="saveSheetNote()">Opslaan</button>
</div>

<div class="toast" id="toast"></div>

<script>
/* ══ DATA ══════════════════════════════════════════════════════════════════ */
const D = <?= $initData ?>;
let lastChangeId = D.last_change_id;
let activeView = 'draaiboek';
let activePhaseIdx = 0;
let sheetItemId = null;
let noteTimers = {};

const CONTACTS = [
  {av:'KJ',name:'Kelly & Jordi',role:'Fotografen',note:'Aanwezig vanaf 08:00 hotel · gehele dag'},
  {av:'ML',name:'Mirjam & Lianne',role:'Make-up artists',note:'Aanwezig 07:30–12:15 hotel'},
  {av:'SU',name:'Sulaika',role:'BABS',note:'Aanwezig 14:30–ca. 16:30'},
  {av:'DL',name:'Daniëlle',role:'Ceremoniemeester',note:'Aanwezig vanaf 13:00'},
  {av:'MA',name:'Marcus',role:'Vader bruid · trouwauto',note:'Aflevering vóór 12:00 — tijdstip NTB'},
  {av:'🌸',name:'Het Bloemenhart',role:'Bloemist',note:'Aflevering boeket + corsages · tijdstip NTB'},
  {av:'TS',name:'Thomas & Sjoerd',role:'DJ & Saxofonist',note:'Aankomst ca. 19:30'},
  {av:'🎺',name:'Brassband',role:'Verrassing voor gasten!',note:'Aankomst 19:30 via melkhuisje · opkomst 20:15'},
  {av:'🏗️',name:'Feestopbouwbedrijf',role:'Opbouw feestzaal',note:'Naam + tijdstip NTB'},
];

/* ══ TIME ══════════════════════════════════════════════════════════════════ */
function toMs(t,date){
  if(!t)return null;
  const[h,m]=t.split(':').map(Number),d=new Date(date);
  d.setHours(h,m,0,0);return d.getTime();
}
function cd(ms){
  const min=Math.round((ms-Date.now())/60000),abs=Math.abs(min);
  const h=Math.floor(abs/60),m=abs%60;
  const val=h>0?`${h}u${m>0?m+'m':''}`:m+'m';
  return{val,late:min<0,soon:min>=0&&min<=30,unit:min>=0?'nog':'te laat'};
}
function itemMs(item,phase){return toMs(item.time_start, phase?.date??'2026-08-09')}

/* ══ AUTO-PHASE: jump to most relevant phase on load ══════════════════════ */
function autoPhase(){
  const now=Date.now();
  // Find the phase that has the next upcoming item closest to now
  let best=-1, bestDiff=Infinity;
  D.phases.forEach((ph,idx)=>{
    const its=D.items.filter(i=>i.phase_id===ph.id&&i.time_start&&!+i.is_done);
    its.forEach(it=>{
      const ms=toMs(it.time_start,ph.date);
      const diff=ms-now;
      if(diff>-3600000&&Math.abs(diff)<bestDiff){bestDiff=Math.abs(diff);best=idx;}
    });
  });
  return best>=0?best:0;
}

/* ══ RENDER: STRIP ══════════════════════════════════════════════════════════ */
function renderStrip(){
  const strip=document.getElementById('phaseStrip');
  strip.innerHTML=D.phases.map((p,i)=>{
    const its=D.items.filter(x=>x.phase_id===p.id);
    const done=its.filter(x=>+x.is_done).length;
    const hasAlert=its.some(x=>{
      if(+x.is_done||!x.time_start)return false;
      const r=cd(toMs(x.time_start,p.date));
      return r.late||r.soon;
    });
    return`<button class="phase-pill${i===activePhaseIdx?' active':''}${hasAlert?' has-alert':''}"
      onclick="goPhase(${i})">
      ${p.emoji} ${p.label}<span class="pc">${done}/${its.length}</span>
    </button>`;
  }).join('');
  // scroll active pill into view
  const pill=strip.querySelectorAll('.phase-pill')[activePhaseIdx];
  if(pill)pill.scrollIntoView({behavior:'smooth',block:'nearest',inline:'center'});
}

/* ══ RENDER: PANELS ═════════════════════════════════════════════════════════ */
function buildPanels(){
  const track=document.getElementById('swipeTrack');
  track.innerHTML=D.phases.map((ph,idx)=>{
    const items=D.items.filter(i=>i.phase_id===ph.id);
    return`<div class="swipe-panel" id="panel-${idx}">${panelHtml(ph,items,idx===activePhaseIdx)}</div>`;
  }).join('');
  positionTrack(false);
}

function panelHtml(ph,items,animate){
  // Next-up banner (only show on the phase that's currently active)
  const now=Date.now();
  let nextItem=null,nextMs=null,nextPh=null;
  for(const p of D.phases){
    for(const it of D.items.filter(i=>i.phase_id===p.id)){
      if(+it.is_done||!it.time_start)continue;
      const ms=toMs(it.time_start,p.date);
      if(ms>now){nextItem=it;nextMs=ms;nextPh=p;break;}
    }
    if(nextItem)break;
  }
  let banner='';
  if(nextItem){
    const r=cd(nextMs);
    banner=`<div class="next-banner">
      <div class="nb-icon">⏭️</div>
      <div class="nb-body">
        <div class="nb-lbl">Volgende</div>
        <div class="nb-what">${esc(nextItem.what)}</div>
      </div>
      <div class="nb-cd">${r.val} ${r.unit}</div>
    </div>`;
  }

  const cards=items.map(item=>{
    const done=+item.is_done;
    const ms=item.time_start?toMs(item.time_start,ph.date):null;
    const timeLbl=item.time_start?(item.time_end?`${item.time_start}–${item.time_end}`:item.time_start):'';
    let cls=''; let chipHtml='';
    if(!done&&ms!==null){
      const r=cd(ms);
      cls=r.late?'is-late':r.soon?'is-soon':'';
      if(+item.is_secret&&!cls)cls='is-secret';
      chipHtml=`<div class="cd ${r.late?'cl':r.soon?'cs':'cf'}">
        <div class="cd-n">${r.val}</div><div class="cd-u">${r.unit}</div></div>`;
    }else if(done){
      cls='is-done';
      chipHtml=`<div class="cd ck"><div class="cd-n">✓</div><div class="cd-u">klaar</div></div>`;
    }
    if(+item.is_secret&&!cls)cls='is-secret';
    const hasNote=!!(item.note&&item.note.trim());

    return`<div class="item-card ${cls}${animate?' slide-in':''}" id="card-${item.id}">
      <div class="item-row" onclick="tapCard(event,'${item.id}')">
        <button class="chk ${done?'on':''}" onclick="toggleDone(event,'${item.id}')">
          <span class="chk-icon">✓</span>
        </button>
        <div class="item-body">
          ${timeLbl||+item.is_secret?`<div class="item-time-row">
            ${timeLbl?`<span class="itime">${timeLbl}</span>`:''}
            ${+item.is_secret?`<span class="badge-s">🎺 Verrassing</span>`:''}
          </div>`:''}
          <div class="iwhat">${esc(item.what)}</div>
          <div class="iwho">${esc(item.who)}</div>
          <div class="iloc">📍 ${esc(item.location)}</div>
          ${hasNote?`<div style="font-size:.66rem;color:var(--gold);margin-top:3px">📝 ${esc(item.note.split('\n')[0]).substring(0,50)}</div>`:''}
        </div>
        ${chipHtml}
      </div>
    </div>`;
  }).join('');

  return`<div class="sec-label">${ph.emoji} ${ph.label}</div>${banner}${cards}`;
}

function refreshPanel(idx){
  const panel=document.getElementById('panel-'+idx);
  if(!panel)return;
  const ph=D.phases[idx];
  const items=D.items.filter(i=>i.phase_id===ph.id);
  panel.innerHTML=panelHtml(ph,items,false);
}

/* ══ NAVIGATION ════════════════════════════════════════════════════════════ */
function goPhase(idx,animate=true){
  activePhaseIdx=idx;
  positionTrack(animate);
  renderStrip();
  // auto-scroll panel to first unchecked item
  setTimeout(()=>{
    const panel=document.getElementById('panel-'+idx);
    const first=panel?.querySelector('.item-card:not(.is-done)');
    if(first)first.scrollIntoView({behavior:'smooth',block:'start'});
  },320);
}

function positionTrack(animate){
  const track=document.getElementById('swipeTrack');
  track.style.transition=animate?'transform .32s cubic-bezier(.4,0,.2,1)':'none';
  track.style.transform=`translateX(-${activePhaseIdx*100}%)`;
}

function showView(v){
  activeView=v;
  const swipeHost=document.getElementById('swipeHost');
  const phaseStrip=document.getElementById('phaseStrip');
  ['Contacten','Corsages','Punten'].forEach(n=>{
    document.getElementById('view'+n).style.display='none';
  });
  if(v==='draaiboek'){
    swipeHost.style.display=''; phaseStrip.style.display='';
  }else{
    swipeHost.style.display='none'; phaseStrip.style.display='none';
    const map={contacten:'Contacten',corsages:'Corsages',punten:'Punten'};
    document.getElementById('view'+map[v]).style.display='';
    if(v==='contacten')renderContacts();
    if(v==='corsages')renderCorsages();
  }
  document.querySelectorAll('.nb').forEach(b=>b.classList.remove('active'));
  document.getElementById('nav-'+v).classList.add('active');
}

/* ══ SWIPE GESTURES ════════════════════════════════════════════════════════ */
(function(){
  let sx=0,sy=0,dragging=false,dx=0;
  const host=document.getElementById('swipeHost');

  host.addEventListener('touchstart',e=>{
    sx=e.touches[0].clientX; sy=e.touches[0].clientY; dragging=true; dx=0;
  },{passive:true});

  host.addEventListener('touchmove',e=>{
    if(!dragging)return;
    dx=e.touches[0].clientX-sx;
    const dy=e.touches[0].clientY-sy;
    if(Math.abs(dy)>Math.abs(dx)){dragging=false;return;}
    const track=document.getElementById('swipeTrack');
    const base=activePhaseIdx*100;
    track.style.transition='none';
    track.style.transform=`translateX(calc(-${base}% + ${dx}px))`;
  },{passive:true});

  host.addEventListener('touchend',()=>{
    if(!dragging){dx=0;return;}
    dragging=false;
    if(dx<-50&&activePhaseIdx<D.phases.length-1)goPhase(activePhaseIdx+1);
    else if(dx>50&&activePhaseIdx>0)goPhase(activePhaseIdx-1);
    else positionTrack(true);
    dx=0;
  });
})();

/* ══ INTERACTIONS ══════════════════════════════════════════════════════════ */
function tapCard(e,id){
  // tap on card body → open notes sheet
  openSheet(id);
}

async function toggleDone(e,id){
  e.stopPropagation();
  const item=D.items.find(i=>i.id===id);
  if(!item)return;
  item.is_done=item.is_done?0:1;
  if(item.is_done){spawnConfetti(e);vibrate();}
  refreshPanel(activePhaseIdx);
  renderStrip();
  updateProgress();
  await api('toggle_item',{id,done:item.is_done});
}

async function toggleCorsage(id){
  const c=D.corsages.find(x=>x.id===id);
  if(!c)return;
  c.is_done=c.is_done?0:1;
  renderCorsages();
  await api('toggle_corsage',{id,done:c.is_done});
}

/* ══ NOTES SHEET ════════════════════════════════════════════════════════════ */
function openSheet(id){
  const item=D.items.find(i=>i.id===id);
  if(!item)return;
  sheetItemId=id;
  document.getElementById('sheetTitle').textContent=item.what.substring(0,50);
  document.getElementById('sheetNote').value=item.note||'';
  document.getElementById('sheetBg').classList.add('open');
  document.getElementById('sheet').classList.add('open');
  setTimeout(()=>document.getElementById('sheetNote').focus(),300);
}

function closeSheet(){
  document.getElementById('sheetBg').classList.remove('open');
  document.getElementById('sheet').classList.remove('open');
}

async function saveSheetNote(){
  const note=document.getElementById('sheetNote').value;
  const item=D.items.find(i=>i.id===sheetItemId);
  if(item)item.note=note;
  closeSheet();
  refreshPanel(activePhaseIdx);
  await api('save_note',{id:sheetItemId,note});
  showToast('Notitie opgeslagen ✓');
}

/* ══ CONTACT & CORSAGE RENDERS ═════════════════════════════════════════════ */
function renderContacts(){
  document.getElementById('contactList').innerHTML=CONTACTS.map(c=>
    `<div class="c-card">
      <div class="c-av">${c.av}</div>
      <div><div class="c-name">${c.name}</div>
      <div class="c-role">${c.role}</div>
      <div class="c-note">${c.note}</div></div>
    </div>`).join('');
}

function renderCorsages(){
  document.getElementById('corsageList').innerHTML=D.corsages.map(c=>{
    const done=+c.is_done;
    return`<div class="cor-card ${done?'done':''}" id="cor-${c.id}">
      <div><div class="cor-name">${esc(c.name)}</div>
      <div class="cor-role">${esc(c.role)}</div></div>
      <button class="cor-btn ${done?'done':''}" onclick="toggleCorsage('${c.id}')">
        <span class="cor-btn-ic">✓</span>
      </button>
    </div>`;
  }).join('');
}

/* ══ PROGRESS ═══════════════════════════════════════════════════════════════ */
function updateProgress(){
  const total=D.items.length, done=D.items.filter(i=>+i.is_done).length;
  const pct=total?(done/total*100).toFixed(0):0;
  document.getElementById('progFill').style.width=pct+'%';
  document.getElementById('progLabel').textContent=`${done} / ${total}`;
}

/* ══ POLLING (elke 15s) ═════════════════════════════════════════════════════ */
const dot=document.getElementById('syncDot');

async function poll(){
  try{
    const r=await fetch(`api.php?action=poll&last_id=${lastChangeId}`);
    if(!r.ok)throw new Error();
    const j=await r.json();
    dot.classList.remove('err');
    if(j.changes&&j.changes.length){
      lastChangeId=j.last_id;
      j.changes.forEach(applyChange);
    }
  }catch{dot.classList.add('err');}
}

function applyChange(ev){
  const{action:a,payload:p}=ev;
  if(a==='toggle_item'){
    const it=D.items.find(i=>i.id===p.id);
    if(it){it.is_done=p.done;refreshPanel(activePhaseIdx);renderStrip();updateProgress();}
  }
  if(a==='save_note'){
    const it=D.items.find(i=>i.id===p.id);
    if(it)it.note=p.note;
    refreshPanel(activePhaseIdx);
  }
  if(a==='toggle_corsage'){
    const c=D.corsages.find(x=>x.id===p.id);
    if(c){c.is_done=p.done;if(activeView==='corsages')renderCorsages();}
  }
  if(a.startsWith('admin_')){
    fetch('api.php?action=get_all').then(r=>r.json()).then(d=>{
      Object.assign(D,d);lastChangeId=d.last_change_id;
      buildPanels();renderStrip();updateProgress();
    });
    showToast('Draaiboek bijgewerkt');
  }
}

setInterval(poll,15000);

/* ══ API ════════════════════════════════════════════════════════════════════ */
async function api(action,payload){
  try{
    await fetch('api.php?action='+action,{
      method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({action,...payload})
    });
  }catch{}
}

/* ══ CONFETTI ═══════════════════════════════════════════════════════════════ */
const CFC=['#c8737a','#c9a96e','#7a9e7e','#d4b0e0','#f5c0a0','#a8d4c0'];
function spawnConfetti(e){
  const x=e.clientX??window.innerWidth/2, y=e.clientY??window.innerHeight/2;
  for(let i=0;i<20;i++){
    const el=document.createElement('div');
    el.className='cf';
    const s=5+Math.random()*7;
    el.style.cssText=`left:${x+(Math.random()-.5)*70}px;top:${y}px;
      background:${CFC[~~(Math.random()*CFC.length)]};
      width:${s}px;height:${s}px;
      border-radius:${Math.random()>.4?'50%':'2px'};
      animation-duration:${.7+Math.random()*.7}s;
      animation-delay:${Math.random()*.12}s`;
    document.body.appendChild(el);
    el.addEventListener('animationend',()=>el.remove());
  }
}

function vibrate(){
  if(navigator.vibrate)navigator.vibrate(40);
}

/* ══ CLOCK + TICK ═══════════════════════════════════════════════════════════ */
function tick(){
  const n=new Date();
  document.getElementById('liveClock').textContent=
    String(n.getHours()).padStart(2,'0')+':'+String(n.getMinutes()).padStart(2,'0');
  if(activeView==='draaiboek'){
    refreshPanel(activePhaseIdx);
    renderStrip();
  }
  updateProgress();
}

function esc(s){
  return String(s||'')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ══ INIT ════════════════════════════════════════════════════════════════════ */
activePhaseIdx=autoPhase();
buildPanels();
renderStrip();
updateProgress();
tick();
setInterval(tick,30000);
poll(); // eerste poll direct
</script>
</body>
</html>
