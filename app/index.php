<?php
require_once __DIR__ . '/db.php';
$pdo = get_db();

// Bootstrap: stuur initiële data mee in de pagina zodat er geen extra request nodig is
$phases = $pdo->query("SELECT * FROM phases ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
$items  = $pdo->query(
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
  <title>💍 Danique & Rens · 9 augustus 2026</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    :root {
      --rose:#c8737a; --rose-light:#f2dada; --rose-pale:#fdf5f5;
      --sage:#7a9e7e; --sage-light:#d8ead9; --sage-pale:#f4f9f4;
      --gold:#c9a96e; --gold-light:#f0e0c0; --gold-pale:#fdf8ef;
      --ink:#2d2420; --ink-soft:#7a6258; --ink-faint:#b8a89e;
      --surface:#fffcf9; --border:rgba(201,169,110,0.18);
      --shadow-sm:0 2px 8px rgba(45,36,32,.07);
      --shadow-md:0 4px 20px rgba(45,36,32,.10);
      --r-md:16px; --r-lg:22px;
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    html{scroll-behavior:smooth}
    body{font-family:'Inter',sans-serif;background:#f7f0e8;color:var(--ink);
      min-height:100vh;padding-bottom:90px;overflow-x:hidden}

    /* ── HERO ── */
    .hero{background:linear-gradient(160deg,#2d2420 0%,#4a3020 50%,#3d2818 100%);
      padding:28px 20px 22px;position:sticky;top:0;z-index:200;
      box-shadow:0 4px 24px rgba(0,0,0,.3)}
    .hero-top{display:flex;justify-content:space-between;align-items:flex-start}
    .hero-names{font-family:'Cormorant Garamond',serif;font-size:2rem;color:#f5ede0;line-height:1}
    .hero-names em{color:var(--gold);font-style:italic}
    .hero-date{font-size:.7rem;color:rgba(245,237,224,.55);margin-top:5px;
      letter-spacing:.14em;text-transform:uppercase;font-weight:500}
    .hero-clock-wrap{text-align:right}
    #liveClock{font-family:'Cormorant Garamond',serif;font-size:2.4rem;color:var(--gold);
      font-variant-numeric:tabular-nums;line-height:1}
    .clock-label{font-size:.6rem;color:rgba(245,237,224,.4);text-transform:uppercase;
      letter-spacing:.1em;margin-top:3px}
    .hero-div{width:100%;height:1px;background:linear-gradient(90deg,transparent,var(--gold),transparent);
      margin:14px 0;opacity:.35}
    .progress-row{display:flex;align-items:center;gap:10px}
    .progress-track{flex:1;height:4px;background:rgba(255,255,255,.1);border-radius:4px;overflow:hidden}
    .progress-fill{height:100%;background:linear-gradient(90deg,var(--rose),var(--gold));
      border-radius:4px;transition:width .6s cubic-bezier(.4,0,.2,1)}
    .progress-text{font-size:.7rem;color:rgba(245,237,224,.5);white-space:nowrap;
      font-variant-numeric:tabular-nums;font-weight:600}

    /* ── SYNC STATUS ── */
    .sync-dot{display:inline-block;width:6px;height:6px;border-radius:50%;
      background:#4a7c59;margin-left:6px;vertical-align:middle;transition:background .4s}
    .sync-dot.offline{background:#c0392b;animation:pulse 1.5s ease-in-out infinite}
    @keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}

    /* ── TABS ── */
    .tab-strip{display:flex;overflow-x:auto;gap:6px;padding:14px 16px 10px;
      background:var(--surface);border-bottom:1px solid var(--border);
      -webkit-overflow-scrolling:touch;scrollbar-width:none}
    .tab-strip::-webkit-scrollbar{display:none}
    .tab-pill{flex-shrink:0;display:flex;align-items:center;gap:5px;
      padding:7px 14px;border-radius:30px;border:1.5px solid var(--border);
      background:transparent;color:var(--ink-soft);font-size:.78rem;font-weight:600;
      font-family:'Inter',sans-serif;cursor:pointer;transition:all .22s ease;white-space:nowrap}
    .tab-pill.active{background:var(--ink);color:#f5ede0;border-color:var(--ink);
      box-shadow:var(--shadow-sm)}
    .tab-pill .pc{background:var(--gold-light);color:#7a5a20;border-radius:20px;
      padding:1px 7px;font-size:.65rem}
    .tab-pill.active .pc{background:rgba(255,255,255,.18);color:#f5ede0}

    /* ── CONTENT ── */
    .content{padding:14px 14px 0}
    .section-label{font-family:'Cormorant Garamond',serif;font-size:1.1rem;
      color:var(--ink-soft);margin:4px 2px 12px}

    /* ── NEXT BANNER ── */
    .next-banner{background:linear-gradient(90deg,var(--rose-pale),var(--gold-pale));
      border:1px solid var(--gold-light);border-radius:var(--r-md);
      padding:11px 14px;margin-bottom:12px;display:flex;align-items:center;gap:10px}
    .nb-icon{font-size:1.2rem;flex-shrink:0}
    .nb-body{flex:1;min-width:0}
    .nb-label{font-size:.62rem;font-weight:700;text-transform:uppercase;
      letter-spacing:.1em;color:var(--ink-faint)}
    .nb-what{font-size:.85rem;font-weight:600;color:var(--ink);margin-top:1px;
      white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .nb-time{font-size:.7rem;color:var(--rose);font-weight:700;white-space:nowrap}

    /* ── ITEM CARD ── */
    .item-card{background:var(--surface);border-radius:var(--r-md);
      box-shadow:var(--shadow-sm);margin-bottom:10px;overflow:hidden;
      border:1px solid var(--border);border-left:4px solid var(--gold);
      transition:transform .12s ease,box-shadow .12s ease}
    .item-card:active{transform:scale(.984)}
    .item-card.is-done{background:var(--sage-pale);border-left-color:var(--sage)}
    .item-card.is-late{background:#fff5f5;border-left-color:var(--rose)}
    .item-card.is-soon{background:#fffbf0;border-left-color:#d4a017}
    .item-card.is-secret{background:#fbf5ff;border-left-color:#9b59b6}

    .item-main{display:flex;align-items:center;padding:13px 13px 11px 14px;
      gap:11px;cursor:pointer;user-select:none}

    .check-circle{flex-shrink:0;width:30px;height:30px;border-radius:50%;
      border:2px solid var(--gold);background:transparent;cursor:pointer;
      display:flex;align-items:center;justify-content:center;
      transition:all .25s cubic-bezier(.4,0,.2,1);position:relative;overflow:hidden}
    .check-circle::after{content:'';position:absolute;inset:0;border-radius:50%;
      background:var(--sage);transform:scale(0);
      transition:transform .25s cubic-bezier(.4,0,.2,1)}
    .check-circle.on{border-color:var(--sage)}
    .check-circle.on::after{transform:scale(1)}
    .check-icon{position:relative;z-index:1;color:white;font-size:.85rem;
      opacity:0;transform:scale(0) rotate(-10deg);
      transition:all .2s .05s cubic-bezier(.4,0,.2,1)}
    .check-circle.on .check-icon{opacity:1;transform:scale(1) rotate(0)}
    .item-card.is-late .check-circle{border-color:var(--rose)}
    .item-card.is-soon .check-circle{border-color:#d4a017}
    .item-card.is-secret .check-circle{border-color:#9b59b6}

    .item-body{flex:1;min-width:0}
    .item-time-row{display:flex;align-items:center;gap:6px;margin-bottom:3px}
    .item-time{font-size:.7rem;font-weight:700;letter-spacing:.06em;color:var(--gold)}
    .item-card.is-done .item-time{color:var(--sage)}
    .item-card.is-late .item-time{color:var(--rose)}
    .item-card.is-soon .item-time{color:#b8880a}
    .badge-secret{font-size:.6rem;font-weight:700;letter-spacing:.06em;
      background:#9b59b6;color:white;border-radius:6px;padding:1px 6px;text-transform:uppercase}
    .item-what{font-size:.9rem;font-weight:600;color:var(--ink);line-height:1.35}
    .item-card.is-done .item-what{text-decoration:line-through;color:var(--ink-faint)}
    .item-who{font-size:.72rem;color:var(--ink-soft);margin-top:3px}
    .item-loc{font-size:.68rem;color:var(--ink-faint);margin-top:2px}

    .cd-chip{flex-shrink:0;text-align:center;min-width:50px}
    .cd-num{font-size:1rem;font-weight:700;font-variant-numeric:tabular-nums;line-height:1}
    .cd-unit{font-size:.58rem;font-weight:600;text-transform:uppercase;
      letter-spacing:.06em;opacity:.75}
    .cd-chip.f{color:var(--ink-faint)} .cd-chip.s{color:#b8880a}
    .cd-chip.l{color:var(--rose)} .cd-chip.d{color:var(--sage)}

    /* ── EXPAND ── */
    .item-expand{max-height:0;overflow:hidden;
      transition:max-height .3s cubic-bezier(.4,0,.2,1)}
    .item-expand.open{max-height:200px}
    .expand-inner{padding:0 14px 12px 56px;border-top:1px solid var(--border)}
    .notes-label{font-size:.62rem;font-weight:700;text-transform:uppercase;
      letter-spacing:.1em;color:var(--ink-faint);margin-top:10px;margin-bottom:5px}
    textarea.notes{width:100%;padding:9px 11px;font-family:'Inter',sans-serif;
      font-size:.82rem;color:var(--ink);background:var(--gold-pale);
      border:1.5px solid var(--gold-light);border-radius:10px;resize:none;
      min-height:64px;transition:border-color .2s;line-height:1.5}
    textarea.notes:focus{outline:none;border-color:var(--gold)}

    /* ── CONTACTS ── */
    .contact-list{display:flex;flex-direction:column;gap:9px}
    .contact-card{background:var(--surface);border-radius:var(--r-md);
      border:1px solid var(--border);box-shadow:var(--shadow-sm);
      padding:13px 14px;display:flex;align-items:center;gap:12px}
    .c-av{width:42px;height:42px;border-radius:50%;flex-shrink:0;
      background:linear-gradient(135deg,var(--gold),var(--rose));
      color:white;font-weight:700;font-size:.9rem;
      display:flex;align-items:center;justify-content:center}
    .c-name{font-size:.9rem;font-weight:700}
    .c-role{font-size:.72rem;color:var(--rose);font-weight:600;margin-top:1px}
    .c-note{font-size:.68rem;color:var(--ink-faint);margin-top:2px}

    /* ── CORSAGES ── */
    .corsage-intro{background:var(--gold-pale);border:1px solid var(--gold-light);
      border-radius:var(--r-md);padding:12px 14px;font-size:.8rem;
      color:var(--ink-soft);margin-bottom:14px;line-height:1.5}
    .corsage-list{display:flex;flex-direction:column;gap:9px}
    .cor-row{background:var(--surface);border-radius:var(--r-md);
      border:1px solid var(--border);box-shadow:var(--shadow-sm);
      padding:13px 14px;display:flex;align-items:center;justify-content:space-between;
      transition:background .2s}
    .cor-row.done{background:var(--sage-pale);border-color:var(--sage-light)}
    .cor-name{font-size:.9rem;font-weight:700}
    .cor-role{font-size:.72rem;color:var(--ink-soft);margin-top:2px}
    .cor-btn{width:32px;height:32px;border-radius:50%;border:2px solid var(--gold);
      background:transparent;cursor:pointer;display:flex;align-items:center;
      justify-content:center;transition:all .22s cubic-bezier(.4,0,.2,1);
      position:relative;overflow:hidden}
    .cor-btn::after{content:'';position:absolute;inset:0;border-radius:50%;
      background:var(--sage);transform:scale(0);transition:transform .22s cubic-bezier(.4,0,.2,1)}
    .cor-btn.done{border-color:var(--sage)}
    .cor-btn.done::after{transform:scale(1)}
    .cor-btn-icon{position:relative;z-index:1;color:white;opacity:0;
      transform:scale(0);transition:all .18s .05s}
    .cor-btn.done .cor-btn-icon{opacity:1;transform:scale(1)}

    /* ── OPEN PUNTEN ── */
    .punt-card{background:var(--surface);border-radius:var(--r-md);
      border:1px solid #f5d0a0;border-left:4px solid #d4a017;
      box-shadow:var(--shadow-sm);padding:12px 14px;margin-bottom:9px;
      font-size:.83rem;color:var(--ink);line-height:1.5}

    /* ── BOTTOM NAV ── */
    .bottom-nav{position:fixed;bottom:0;left:0;right:0;
      background:rgba(255,252,249,.96);backdrop-filter:blur(12px);
      -webkit-backdrop-filter:blur(12px);border-top:1px solid var(--border);
      display:flex;justify-content:space-around;
      padding:10px 0 max(12px,env(safe-area-inset-bottom));
      z-index:300;box-shadow:0 -4px 20px rgba(45,36,32,.08)}
    .nav-btn{display:flex;flex-direction:column;align-items:center;gap:3px;
      background:none;border:none;cursor:pointer;color:var(--ink-faint);
      font-size:.6rem;font-weight:600;font-family:'Inter',sans-serif;
      text-transform:uppercase;letter-spacing:.06em;padding:4px 14px;transition:color .2s}
    .nav-btn.active{color:var(--ink)}
    .nav-icon{font-size:1.45rem;line-height:1;transition:transform .2s cubic-bezier(.4,0,.2,1)}
    .nav-btn.active .nav-icon{transform:scale(1.15)}

    /* ── VIEWS ── */
    .view{display:none}
    .view.active{display:block}

    /* ── CONFETTI ── */
    .cf{position:fixed;pointer-events:none;z-index:9999;
      animation:cffall 1.1s ease-out forwards}
    @keyframes cffall{
      0%{transform:translateY(0) rotate(0deg) scale(1);opacity:1}
      100%{transform:translateY(160px) rotate(540deg) scale(.5);opacity:0}
    }

    /* ── TOAST ── */
    .toast{position:fixed;bottom:80px;left:50%;
      transform:translateX(-50%) translateY(30px);opacity:0;
      background:var(--ink);color:#f5ede0;padding:9px 18px;
      border-radius:30px;font-size:.8rem;font-weight:600;
      transition:all .3s ease;z-index:999;white-space:nowrap;pointer-events:none}
    .toast.show{transform:translateX(-50%) translateY(0);opacity:1}
  </style>
</head>
<body>

<!-- ── HERO ── -->
<header class="hero">
  <div class="hero-top">
    <div>
      <div class="hero-names">Danique <em>&</em> Rens</div>
      <div class="hero-date">Zaterdag · 9 augustus 2026 · Hoeve Zzamen
        <span class="sync-dot" id="syncDot" title="Live verbinding"></span>
      </div>
    </div>
    <div class="hero-clock-wrap">
      <div id="liveClock">--:--</div>
      <div class="clock-label">Nu</div>
    </div>
  </div>
  <div class="hero-div"></div>
  <div class="progress-row">
    <div class="progress-track">
      <div class="progress-fill" id="progressFill" style="width:0%"></div>
    </div>
    <div class="progress-text" id="progressText">0 / 0</div>
  </div>
</header>

<!-- ── VIEWS ── -->
<div class="view active" id="view-draaiboek">
  <div class="tab-strip" id="tabStrip"></div>
  <div class="content" id="phaseContent"></div>
</div>

<div class="view" id="view-contacten">
  <div class="content">
    <div class="section-label">Leveranciers & contacten</div>
    <div class="contact-list" id="contactList"></div>
  </div>
</div>

<div class="view" id="view-corsages">
  <div class="content">
    <div class="section-label">Corsages & boeket</div>
    <div class="corsage-intro">🌸 Geleverd door <strong>Het Bloemenhart</strong> (Berkel en Rodenrijs).<br>Vink af zodra de corsage is overhandigd.</div>
    <div class="corsage-list" id="corsageList"></div>
  </div>
</div>

<div class="view" id="view-punten">
  <div class="content">
    <div class="section-label">Openstaande punten</div>
    <?php
    $punten = [
        'Locatie fotoshoot & geloftes: nader te bepalen',
        'Tijdstip aflevering trouwauto door Marcus (moet vóór 12:00 aanwezig zijn)',
        'Tijdstip aflevering bruidsboeket & corsages door Het Bloemenhart',
        'Naam feestopbouwbedrijf + aankomsttijd: nader te bepalen',
        'Eindmoment feest (last dance / uitzwaai): nader te bepalen',
        'Verantwoordelijkheden per moment: nader in te vullen',
    ];
    foreach ($punten as $p):
    ?><div class="punt-card">⚠️ <?= htmlspecialchars($p) ?></div><?php endforeach ?>
  </div>
</div>

<!-- ── BOTTOM NAV ── -->
<nav class="bottom-nav">
  <button class="nav-btn active" id="nav-draaiboek" onclick="showView('draaiboek')">
    <span class="nav-icon">📋</span>Draaiboek
  </button>
  <button class="nav-btn" id="nav-contacten" onclick="showView('contacten')">
    <span class="nav-icon">👥</span>Contacten
  </button>
  <button class="nav-btn" id="nav-corsages" onclick="showView('corsages')">
    <span class="nav-icon">🌸</span>Corsages
  </button>
  <button class="nav-btn" id="nav-punten" onclick="showView('punten')">
    <span class="nav-icon">⚠️</span>Openstaand
  </button>
</nav>

<div class="toast" id="toast"></div>

<script>
/* ── INITIAL DATA (from PHP) ── */
const INIT = <?= $initData ?>;

const CONTACTS = [
  {av:'KJ',name:'Kelly & Jordi',role:'Fotografen',note:'Aanwezig vanaf 08:00 hotel · gehele dag'},
  {av:'ML',name:'Mirjam & Lianne',role:'Make-up artists',note:'Aanwezig 07:30–12:15, hotel'},
  {av:'SU',name:'Sulaika',role:'BABS (Burgerlijk Ambtenaar)',note:'Aanwezig 14:30–ca. 16:30'},
  {av:'DL',name:'Daniëlle',role:'Ceremoniemeester',note:'Aanwezig vanaf 13:00'},
  {av:'MA',name:'Marcus',role:'Vader bruid · trouwauto',note:'Aflevering vóór 12:00 — tijdstip NTB'},
  {av:'🌸',name:'Het Bloemenhart',role:'Bloemist, Berkel en Rodenrijs',note:'Aflevering boeket + corsages · tijdstip NTB'},
  {av:'TS',name:'Thomas & Sjoerd',role:'DJ & Saxofonist',note:'Aankomst ca. 19:30'},
  {av:'🎺',name:'Brassband',role:'Verrassing voor gasten!',note:'Aankomst 19:30 via melkhuisje · opkomst 20:15'},
  {av:'🏗️',name:'Feestopbouwbedrijf',role:'Opbouw feestzaal',note:'Naam + tijdstip nader te bepalen'},
];

/* ── STATE ── */
let data = INIT;
let activePhase = localStorage.getItem('dr_phase') || (data.phases[0]?.id ?? '');
let activeView  = 'draaiboek';
let noteTimers  = {};

/* ── TIME HELPERS ── */
function toMs(timeStr, date) {
  if (!timeStr) return null;
  const [h,m] = timeStr.split(':').map(Number);
  const d = new Date(date);
  d.setHours(h,m,0,0);
  return d.getTime();
}

function fmtCd(ms) {
  const mins = Math.round((ms - Date.now()) / 60000);
  const abs = Math.abs(mins), h = Math.floor(abs/60), m = abs%60;
  const val = h > 0 ? `${h}u${m>0?m+'m':''}` : `${m}m`;
  return {val, unit: mins >= 0 ? 'nog' : 'te laat', late: mins < 0, soon: mins >= 0 && mins <= 30};
}

/* ── RENDER: TABS ── */
function renderTabs() {
  const strip = document.getElementById('tabStrip');
  strip.innerHTML = data.phases.map(p => {
    const its = data.items.filter(i => i.phase_id === p.id);
    const done = its.filter(i => +i.is_done).length;
    return `<button class="tab-pill ${p.id===activePhase?'active':''}" onclick="switchPhase('${p.id}')">
      ${p.emoji} ${p.label} <span class="pc">${done}/${its.length}</span>
    </button>`;
  }).join('');
}

/* ── RENDER: PHASE ── */
function renderPhase() {
  const phase = data.phases.find(p => p.id === activePhase);
  if (!phase) return;
  const items = data.items.filter(i => i.phase_id === phase.id);

  // Next-up banner
  const now = Date.now();
  let nextItem = null, nextMs = null;
  for (const ph of data.phases) {
    for (const it of data.items.filter(i => i.phase_id === ph.id)) {
      if (+it.is_done || !it.time_start) continue;
      const ms = toMs(it.time_start, ph.date);
      if (ms > now) { nextItem = it; nextMs = ms; break; }
    }
    if (nextItem) break;
  }

  let banner = '';
  if (nextItem) {
    const cd = fmtCd(nextMs);
    banner = `<div class="next-banner">
      <div class="nb-icon">⏭️</div>
      <div class="nb-body">
        <div class="nb-label">Volgende activiteit</div>
        <div class="nb-what">${esc(nextItem.what)}</div>
      </div>
      <div class="nb-time">${cd.val} ${cd.unit}</div>
    </div>`;
  }

  const cards = items.map(item => {
    const done = +item.is_done;
    const ms   = item.time_start ? toMs(item.time_start, phase.date) : null;
    const timeLbl = item.time_start
      ? item.time_end ? `${item.time_start} – ${item.time_end}` : item.time_start
      : '';

    let cls = done ? 'is-done' : '';
    let cdHtml = '';
    if (!done && ms !== null) {
      const cd = fmtCd(ms);
      if (!cls) cls = cd.late ? 'is-late' : cd.soon ? 'is-soon' : '';
      if (+item.is_secret && !cd.late && !cd.soon) cls = 'is-secret';
      cdHtml = `<div class="cd-chip ${cd.late?'l':cd.soon?'s':'f'}">
        <div class="cd-num">${cd.val}</div>
        <div class="cd-unit">${cd.unit}</div>
      </div>`;
    } else if (done) {
      cdHtml = `<div class="cd-chip d"><div class="cd-num">✓</div><div class="cd-unit">klaar</div></div>`;
    }
    if (+item.is_secret && !cls) cls = 'is-secret';

    const expanded = localStorage.getItem('exp_'+item.id) === '1';

    return `<div class="item-card ${cls}" id="card-${item.id}">
      <div class="item-main" onclick="toggleExpand('${item.id}')">
        <button class="check-circle ${done?'on':''}" onclick="toggleDone(event,'${item.id}')">
          <span class="check-icon">✓</span>
        </button>
        <div class="item-body">
          ${timeLbl||+item.is_secret ? `<div class="item-time-row">
            ${timeLbl ? `<span class="item-time">${timeLbl}</span>` : ''}
            ${+item.is_secret ? `<span class="badge-secret">🎺 Verrassing</span>` : ''}
          </div>` : ''}
          <div class="item-what">${esc(item.what)}</div>
          <div class="item-who">${esc(item.who)}</div>
          <div class="item-loc">📍 ${esc(item.location)}</div>
        </div>
        ${cdHtml}
      </div>
      <div class="item-expand ${expanded?'open':''}" id="exp-${item.id}">
        <div class="expand-inner">
          <div class="notes-label">Notitie</div>
          <textarea class="notes" placeholder="Voeg een notitie toe…"
            oninput="queueNote('${item.id}',this.value)">${esc(item.note||'')}</textarea>
        </div>
      </div>
    </div>`;
  }).join('');

  document.getElementById('phaseContent').innerHTML =
    `<div class="section-label">${phase.emoji} ${phase.label}</div>${banner}${cards}`;
}

/* ── RENDER: CONTACTS ── */
function renderContacts() {
  document.getElementById('contactList').innerHTML = CONTACTS.map(c =>
    `<div class="contact-card">
      <div class="c-av">${c.av}</div>
      <div>
        <div class="c-name">${c.name}</div>
        <div class="c-role">${c.role}</div>
        <div class="c-note">${c.note}</div>
      </div>
    </div>`).join('');
}

/* ── RENDER: CORSAGES ── */
function renderCorsages() {
  document.getElementById('corsageList').innerHTML = data.corsages.map(c => {
    const done = +c.is_done;
    return `<div class="cor-row ${done?'done':''}" id="cor-${c.id}">
      <div>
        <div class="cor-name">${esc(c.name)}</div>
        <div class="cor-role">${esc(c.role)}</div>
      </div>
      <button class="cor-btn ${done?'done':''}" onclick="toggleCorsage('${c.id}')">
        <span class="cor-btn-icon">✓</span>
      </button>
    </div>`;
  }).join('');
}

/* ── PROGRESS ── */
function updateProgress() {
  const total = data.items.length;
  const done  = data.items.filter(i => +i.is_done).length;
  const pct   = total ? (done/total*100).toFixed(0) : 0;
  document.getElementById('progressFill').style.width = pct + '%';
  document.getElementById('progressText').textContent = `${done} / ${total}`;
}

/* ── INTERACTIONS ── */
function switchPhase(id) {
  activePhase = id;
  localStorage.setItem('dr_phase', id);
  renderTabs();
  renderPhase();
}

async function toggleDone(e, id) {
  e.stopPropagation();
  const item = data.items.find(i => i.id === id);
  if (!item) return;
  item.is_done = item.is_done ? 0 : 1;
  if (item.is_done) spawnConfetti(e);
  renderTabs(); renderPhase(); updateProgress();
  await api('toggle_item', {id, done: item.is_done});
}

function toggleExpand(id) {
  const el = document.getElementById('exp-'+id);
  if (!el) return;
  const open = el.classList.toggle('open');
  localStorage.setItem('exp_'+id, open ? '1' : '0');
}

function queueNote(id, text) {
  const item = data.items.find(i => i.id === id);
  if (item) item.note = text;
  clearTimeout(noteTimers[id]);
  noteTimers[id] = setTimeout(() => api('save_note', {id, note: text}), 800);
}

async function toggleCorsage(id) {
  const c = data.corsages.find(x => x.id === id);
  if (!c) return;
  c.is_done = c.is_done ? 0 : 1;
  renderCorsages();
  await api('toggle_corsage', {id, done: c.is_done});
}

/* ── API ── */
async function api(action, payload) {
  try {
    await fetch('api.php?action=' + action, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({action, ...payload})
    });
  } catch(e) { console.warn('API error', e); }
}

/* ── VIEWS ── */
function showView(v) {
  activeView = v;
  document.querySelectorAll('.view').forEach(el => el.classList.remove('active'));
  document.getElementById('view-'+v).classList.add('active');
  document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('nav-'+v).classList.add('active');
  if (v === 'contacten') renderContacts();
  if (v === 'corsages')  renderCorsages();
}

/* ── REALTIME (SSE) ── */
let sseLastId = INIT.last_change_id;

function connectSSE() {
  const dot = document.getElementById('syncDot');
  const es = new EventSource(`api.php?action=stream&last_id=${sseLastId}`);

  es.addEventListener('change', e => {
    const ev = JSON.parse(e.data);
    sseLastId = parseInt(e.lastEventId) || sseLastId;
    handleRemoteChange(ev);
  });

  es.onopen = () => dot.classList.remove('offline');
  es.onerror = () => {
    dot.classList.add('offline');
    es.close();
    setTimeout(connectSSE, 5000); // reconnect
  };
}

function handleRemoteChange(ev) {
  const {action, payload} = ev;

  if (action === 'toggle_item') {
    const item = data.items.find(i => i.id === payload.id);
    if (item) { item.is_done = payload.done; renderTabs(); renderPhase(); updateProgress(); }
  }
  if (action === 'save_note') {
    const item = data.items.find(i => i.id === payload.id);
    if (item) item.note = payload.note;
    // update textarea if visible
    const ta = document.querySelector(`#exp-${payload.id} textarea`);
    if (ta && document.activeElement !== ta) ta.value = payload.note;
  }
  if (action === 'toggle_corsage') {
    const c = data.corsages.find(x => x.id === payload.id);
    if (c) { c.is_done = payload.done; if (activeView === 'corsages') renderCorsages(); }
  }
  if (['admin_save_item','admin_delete_item','admin_reorder','admin_reset'].includes(action)) {
    // Full refresh on admin changes
    fetch('api.php?action=get_all')
      .then(r => r.json())
      .then(d => { data = d; sseLastId = d.last_change_id; renderTabs(); renderPhase(); updateProgress(); });
    showToast('Draaiboek bijgewerkt door beheerder');
  }
}

/* ── CONFETTI ── */
const CF_COLORS = ['#c8737a','#c9a96e','#7a9e7e','#d4b0e0','#f5c0a0'];
function spawnConfetti(e) {
  const x = e.clientX || window.innerWidth/2;
  const y = e.clientY || window.innerHeight/2;
  for (let i=0; i<18; i++) {
    const el = document.createElement('div');
    el.className = 'cf';
    const size = 6 + Math.random()*6;
    el.style.cssText = `left:${x+(Math.random()-.5)*60}px;top:${y}px;
      background:${CF_COLORS[~~(Math.random()*CF_COLORS.length)]};
      width:${size}px;height:${size}px;
      border-radius:${Math.random()>.5?'50%':'3px'};
      animation-duration:${.8+Math.random()*.6}s;
      animation-delay:${Math.random()*.15}s`;
    document.body.appendChild(el);
    el.addEventListener('animationend', () => el.remove());
  }
}

/* ── TOAST ── */
function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2800);
}

/* ── CLOCK & TICK ── */
function tick() {
  const now = new Date();
  document.getElementById('liveClock').textContent =
    String(now.getHours()).padStart(2,'0') + ':' + String(now.getMinutes()).padStart(2,'0');
  if (activeView === 'draaiboek') renderPhase();
  updateProgress();
}

function esc(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ── INIT ── */
renderTabs();
renderPhase();
updateProgress();
tick();
setInterval(tick, 30000);
connectSSE();
</script>
</body>
</html>
