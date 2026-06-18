<?php
session_start();
require_once __DIR__ . '/config.php';

// ── Viewer login ──────────────────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vpw'])) {
    if ($_POST['vpw'] === VIEWER_PASSWORD) {
        $_SESSION['viewer'] = true;
        $_SESSION['viewer_at'] = time();
    } else {
        $loginError = true;
    }
}
// 4-uur sessie-verval
if (!empty($_SESSION['viewer_at']) && time() - $_SESSION['viewer_at'] > 14400) {
    session_destroy(); session_start();
}
if (empty($_SESSION['viewer'])): ?><!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0"/>
<title>💍 Danique & Rens</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,600;1,400&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<style>
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
html,body{min-height:100%;font-family:'Inter',sans-serif;
  background:linear-gradient(160deg,#1e1510 0%,#3a2414 55%,#2a1c12 100%);
  display:flex;align-items:center;justify-content:center;padding:20px}
.login-card{background:rgba(255,252,249,.97);border-radius:24px;
  padding:36px 28px 32px;width:100%;max-width:360px;
  box-shadow:0 20px 60px rgba(0,0,0,.35)}
.login-ring{font-size:2.6rem;text-align:center;margin-bottom:8px}
.login-title{font-family:'Cormorant Garamond',serif;font-size:1.9rem;
  text-align:center;color:#2d2420}
.login-sub{font-size:.72rem;color:#7a6258;text-align:center;
  margin:5px 0 26px;letter-spacing:.06em;text-transform:uppercase}
.fg{margin-bottom:16px}
label{display:block;font-size:.7rem;font-weight:700;text-transform:uppercase;
  letter-spacing:.08em;color:#7a6258;margin-bottom:6px}
input[type=password]{width:100%;padding:12px 14px;border:1.5px solid rgba(201,169,110,.3);
  border-radius:12px;font-family:'Inter',sans-serif;font-size:1rem;color:#2d2420;
  background:#fffcf9;-webkit-appearance:none;transition:border-color .2s}
input[type=password]:focus{outline:none;border-color:#c9a96e}
.btn-login{width:100%;padding:13px;border:none;border-radius:12px;
  background:linear-gradient(135deg,#3a2414,#5a3820);color:#f5ede0;
  font-family:'Inter',sans-serif;font-weight:700;font-size:.95rem;cursor:pointer;
  transition:opacity .2s;margin-top:4px}
.btn-login:active{opacity:.8}
.err{font-size:.8rem;color:#c0392b;text-align:center;margin-bottom:12px}
</style>
</head>
<body>
<div class="login-card">
  <div class="login-ring">💍</div>
  <div class="login-title">Danique <em style="color:#c9a96e;font-style:italic">&</em> Rens</div>
  <div class="login-sub">8 augustus 2026 · Hoeve Zzamen</div>
  <?php if (!empty($loginError)): ?>
    <div class="err">Ongeldig wachtwoord, probeer opnieuw.</div>
  <?php endif ?>
  <form method="POST">
    <div class="fg">
      <label>Wachtwoord</label>
      <input type="password" name="vpw" autofocus placeholder="••••••••" required/>
    </div>
    <button class="btn-login" type="submit">Inloggen</button>
  </form>
</div>
</body>
</html>
<?php exit; endif;

require_once __DIR__ . '/db.php';
$pdo = get_db();

$phases   = $pdo->query("SELECT * FROM phases ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
$items    = $pdo->query(
    "SELECT i.*, COALESCE(s.is_done,0) as is_done, COALESCE(s.note,'') as note,
            s.on_time, s.checked_at
     FROM items i LEFT JOIN state s ON s.item_id=i.id
     ORDER BY i.phase_id, i.time_start IS NULL, i.date, i.time_start, i.sort_order"
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
  --sh:0 2px 16px rgba(45,36,32,.09);
  --r:14px;
  --nav-h:64px;
  --strip-h:50px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--ink)}

/* ═══════════════════════════════════════════════
   HERO HEADER — glassmorphism + shrink on scroll
   ═══════════════════════════════════════════════ */
.sticky-head{position:sticky;top:0;z-index:200}
.hero{
  /* Tall state */
  background:linear-gradient(160deg,#1e1510 0%,#3a2414 55%,#2a1c12 100%);
  padding-top:env(safe-area-inset-top,0);
  transition:background .4s,box-shadow .4s;
  overflow:hidden;
}

/* decorative soft-light orbs behind content */
.hero::before{
  content:'';position:absolute;inset:0;
  background:
    radial-gradient(ellipse 80% 60% at 110% -20%, rgba(201,169,110,.18) 0%, transparent 60%),
    radial-gradient(ellipse 50% 80% at -10% 120%, rgba(200,115,122,.12) 0%, transparent 60%);
  pointer-events:none;
}

/* glass shimmer line */
.hero::after{
  content:'';position:absolute;
  top:0;left:-60%;width:40%;height:100%;
  background:linear-gradient(105deg,transparent 40%,rgba(255,255,255,.06) 50%,transparent 60%);
  animation:sheen 8s ease-in-out infinite;
  pointer-events:none;
}
@keyframes sheen{0%,100%{left:-60%}50%{left:120%}}

.hero-inner{
  padding:14px 18px 10px;
  transition:padding .35s cubic-bezier(.4,0,.2,1);
}
.hero-top{display:flex;justify-content:space-between;align-items:flex-start}

/* title block */
.hero-title{
  font-family:'Cormorant Garamond',serif;
  font-size:1.75rem;color:#f5ede0;line-height:1;
  transition:font-size .35s,opacity .35s;
}
.hero-title em{color:var(--gold);font-style:italic}
.hero-sub{
  font-size:.6rem;color:rgba(245,237,224,.45);
  margin-top:5px;letter-spacing:.14em;text-transform:uppercase;
  transition:opacity .35s,max-height .35s;
  overflow:hidden;max-height:20px;
}

/* clock */
#liveClock{
  font-family:'Cormorant Garamond',serif;
  font-size:2.2rem;color:var(--gold);
  font-variant-numeric:tabular-nums;line-height:1;
  transition:font-size .35s;
}
.clock-sub{
  font-size:.58rem;color:rgba(245,237,224,.35);
  letter-spacing:.1em;text-transform:uppercase;
  margin-top:3px;display:flex;align-items:center;justify-content:flex-end;gap:4px;
  transition:opacity .35s;
}
.sync-dot{width:6px;height:6px;border-radius:50%;background:#4a7c59;transition:background .4s;flex-shrink:0}
.sync-dot.err{background:var(--rose);animation:blink 1.4s infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}

/* progress */
.hero-progress{
  padding:0 18px 12px;
  display:flex;align-items:center;gap:10px;
  transition:max-height .35s,opacity .35s,padding .35s;
  overflow:hidden;max-height:40px;
}
.prog-track{flex:1;height:3px;background:rgba(255,255,255,.1);border-radius:3px;overflow:hidden}
.prog-fill{
  height:100%;
  background:linear-gradient(90deg,var(--rose),var(--gold));
  border-radius:3px;transition:width .7s cubic-bezier(.4,0,.2,1);
}
.prog-label{font-size:.65rem;color:rgba(245,237,224,.4);font-variant-numeric:tabular-nums;font-weight:600;white-space:nowrap}

/* ── COMPACT (scrolled) ── */
.hero.compact{
  background:rgba(30,21,16,.95);
  box-shadow:0 2px 24px rgba(0,0,0,.4);
}
.hero.compact .hero-inner{padding:8px 16px 6px}
.hero.compact .hero-title{font-size:1.1rem}
.hero.compact .hero-sub{opacity:0;max-height:0;margin:0}
.hero.compact #liveClock{font-size:1.4rem}
.hero.compact .clock-sub{opacity:0}
.hero.compact .hero-progress{max-height:0;opacity:0;padding-bottom:0}
.hero.compact .ticker{max-height:0;opacity:0;padding-bottom:0}

/* ── AMBIENT ACHTERGROND ── */
#ambientOrb{
  position:fixed;top:-25vh;right:-25vw;
  width:90vmax;height:90vmax;border-radius:50%;
  pointer-events:none;z-index:0;opacity:.13;
  transition:background 90s linear;
}
.content,.static-view{position:relative;z-index:1}

/* ── LIVE TICKER ── */
.ticker{
  padding:0 16px 9px;
  max-height:38px;opacity:1;
  overflow:hidden;
  transition:max-height .35s,opacity .35s,padding .35s;
}
.ticker-inner{
  display:flex;align-items:center;gap:8px;
  background:rgba(255,255,255,.08);
  border-radius:20px;padding:5px 13px 5px 10px;
}
.ticker-dot{
  width:6px;height:6px;border-radius:50%;
  background:var(--gold);flex-shrink:0;
  transition:background .4s;
}
.ticker-dot.urgent{background:var(--rose);animation:blink 1s infinite}
.ticker-what{
  flex:1;min-width:0;font-size:.68rem;
  color:rgba(245,237,224,.72);
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.ticker-cd{
  font-size:.76rem;font-weight:700;
  font-variant-numeric:tabular-nums;
  color:var(--gold);flex-shrink:0;white-space:nowrap;
  transition:color .4s;
}
.ticker-cd.urgent{color:var(--rose)}

/* ── SWIPE-TO-COMPLETE ── */
.swipe-wrap{position:relative;overflow:hidden;border-radius:var(--r)}
.swipe-bg{
  position:absolute;inset:0;
  display:flex;align-items:center;
  font-size:1.3rem;opacity:0;
  pointer-events:none;
}
.swipe-bg-r{
  justify-content:flex-start;padding-left:18px;
  background:linear-gradient(90deg,var(--sage) 0%,rgba(122,158,126,0) 80%);
}
.swipe-bg-l{
  justify-content:flex-end;padding-right:18px;
  background:linear-gradient(270deg,var(--gold) 0%,rgba(201,169,110,0) 80%);
}
.tl-card{position:relative;z-index:1}

.phase-strip{
  display:flex;overflow-x:auto;gap:6px;padding:9px 14px;
  background:rgba(255,252,249,.98);
  border-bottom:1px solid var(--border);
  -webkit-overflow-scrolling:touch;scrollbar-width:none;
}
.phase-strip::-webkit-scrollbar{display:none}
.phase-pill{
  flex-shrink:0;display:flex;align-items:center;gap:5px;
  padding:6px 13px;border-radius:30px;border:1.5px solid var(--border);
  background:transparent;color:var(--ink-soft);font-size:.75rem;font-weight:600;
  font-family:'Inter',sans-serif;cursor:pointer;transition:all .2s;white-space:nowrap;
}
.phase-pill.active{background:var(--ink);color:#f5ede0;border-color:var(--ink)}
.phase-pill .pc{font-size:.62rem;padding:1px 6px;border-radius:10px;background:var(--gold-light);color:#7a5a20}
.phase-pill.active .pc{background:rgba(255,255,255,.2);color:#f5ede0}
.phase-pill.has-alert .pc{background:var(--rose-light);color:var(--rose)}

/* ── CONTENT ── */
.content{padding:16px 16px calc(var(--nav-h) + 24px)}
.static-view{display:none;padding:16px 16px calc(var(--nav-h) + 24px)}

/* ═════════════════════════════════
   FASE-KLEUREN (theming per fase)
   ═════════════════════════════════ */
:root{
  --phase-accent:var(--gold);
  --phase-dark:#3a2414;
  --phase-bg:var(--bg);
  --phase-line:var(--gold);
}
body{transition:background-color .55s ease}

/* ═════════════════════════════════
   TIMELINE
   ═════════════════════════════════ */
.tl-phase-label{
  font-family:'Cormorant Garamond',serif;
  font-size:1.25rem;color:var(--ink-soft);
  margin-bottom:14px;padding-left:44px;
  display:flex;align-items:center;gap:8px;
}

.timeline{position:relative;padding-left:44px}

/* vertical line — zelf-tekenend via scaleY */
.timeline::before{
  content:'';position:absolute;
  left:14px;top:6px;bottom:0;width:2px;
  background:linear-gradient(to bottom,
    var(--phase-line) 0%,
    rgba(201,169,110,.5) 60%,
    rgba(201,169,110,.06) 100%);
  border-radius:2px;
  transform-origin:top;
  transform:scaleY(0);
  transition:transform .65s cubic-bezier(.4,0,.2,1), background .55s ease;
}
.timeline.drawn::before{transform:scaleY(1)}

/* ── STAGGER ENTRANCE ── */
@keyframes cardIn{
  from{opacity:0;transform:translateX(22px)}
  to{opacity:1;transform:translateX(0)}
}
@keyframes cardInDone{
  from{opacity:0;transform:translateX(-18px)}
  to{opacity:.58;transform:translateX(-5px)}
}

/* ── TIMELINE ITEM ── */
.tl-item{
  position:relative;margin-bottom:10px;
  transition:transform .3s cubic-bezier(.4,0,.2,1),opacity .3s;
}
.tl-item.entering{animation:cardIn .32s cubic-bezier(.2,0,.2,1) both}
.tl-item.entering.is-done{animation:cardInDone .32s cubic-bezier(.2,0,.2,1) both}

/* ── SPOTLIGHT: eerste actieve item ── */
.tl-item.spotlight .tl-card{
  box-shadow:0 0 0 2px var(--phase-accent),
             0 6px 28px rgba(201,169,110,.28);
  animation:glow-pulse 2.2s ease-in-out infinite;
}
.tl-item.spotlight .tl-dot{
  box-shadow:0 0 0 5px rgba(201,169,110,.18),
             0 0 14px rgba(201,169,110,.25);
  animation:pulse-dot 2.2s ease-in-out infinite;
}
@keyframes glow-pulse{
  0%,100%{box-shadow:0 0 0 2px var(--phase-accent),0 4px 18px rgba(201,169,110,.2)}
  50%{box-shadow:0 0 0 3px var(--phase-accent),0 8px 34px rgba(201,169,110,.38)}
}

/* dot on the line */
.tl-dot{
  position:absolute;left:-37px;top:16px;
  width:16px;height:16px;border-radius:50%;
  border:2.5px solid var(--phase-accent);background:var(--surface);
  z-index:1;transition:all .3s cubic-bezier(.34,1.56,.64,1), border-color .55s ease;
  box-shadow:0 0 0 3px rgba(201,169,110,.12);
}
.tl-item.is-done .tl-dot{
  background:var(--sage);border-color:var(--sage);
  box-shadow:0 0 0 4px rgba(122,158,126,.15);
}
.tl-item.is-late .tl-dot{background:var(--rose);border-color:var(--rose);
  box-shadow:0 0 0 4px rgba(200,115,122,.2),0 0 10px rgba(200,115,122,.3)}
.tl-item.is-soon .tl-dot{background:#d4a017;border-color:#d4a017;
  animation:pulse-dot 1.8s ease-in-out infinite}
.tl-item.is-secret .tl-dot{background:var(--purple);border-color:var(--purple)}
@keyframes pulse-dot{0%,100%{box-shadow:0 0 0 3px rgba(212,160,23,.2)}
  50%{box-shadow:0 0 0 7px rgba(212,160,23,.08)}}

/* connector tick */
.tl-dot::after{
  content:'';position:absolute;
  top:50%;left:100%;width:10px;height:1.5px;
  background:var(--gold);opacity:.4;transform:translateY(-50%);
}
.tl-item.is-done .tl-dot::after{background:var(--sage)}

/* ── CARD inside timeline ── */
.tl-card{
  background:var(--surface);
  border-radius:var(--r);
  border:1px solid var(--border);
  box-shadow:var(--sh);
  overflow:hidden;
  cursor:pointer;
  transition:transform .12s,box-shadow .2s;
}
.tl-card:active{transform:scale(.98)}

.tl-item.is-done{
  transform:translateX(-5px);
  opacity:.58;
}
.tl-item.is-done .tl-card{
  background:var(--sage-pale);
  border-color:var(--sage-light);
  box-shadow:none;
}
.tl-item.is-late .tl-card{border-color:var(--rose-light);background:#fff5f5}
.tl-item.is-soon .tl-card{border-color:#f5d88a;background:#fffbf0}
.tl-item.is-secret .tl-card{border-color:#d4b0e0;background:var(--purple-pale)}
.tl-item.queued{opacity:.32;filter:saturate(.25);transform:scale(.975)}
.tl-item.queued .tl-dot{opacity:.4}

/* left accent stripe */
.tl-card::before{
  content:'';display:block;height:3px;
  background:linear-gradient(90deg,var(--phase-accent),rgba(201,169,110,.15));
  transition:background .3s, background-color .55s;
}
.tl-item.is-done .tl-card::before{background:linear-gradient(90deg,var(--sage),rgba(122,158,126,.2))}
.tl-item.is-late .tl-card::before{background:linear-gradient(90deg,var(--rose),rgba(200,115,122,.2))}
.tl-item.is-soon .tl-card::before{background:linear-gradient(90deg,#d4a017,rgba(212,160,23,.2))}
.tl-item.is-secret .tl-card::before{background:linear-gradient(90deg,var(--purple),rgba(155,89,182,.2))}

.tl-row{display:flex;align-items:center;padding:10px 12px 10px 11px;gap:10px}

/* check button */
.chk{
  flex-shrink:0;width:28px;height:28px;border-radius:50%;
  border:2px solid var(--gold);cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  position:relative;overflow:hidden;background:transparent;
  transition:border-color .2s;
}
.chk::after{content:'';position:absolute;inset:0;border-radius:50%;
  background:var(--sage);transform:scale(0);
  transition:transform .28s cubic-bezier(.34,1.56,.64,1)}
.chk.on{border-color:var(--sage)}.chk.on::after{transform:scale(1)}
.chk-icon{position:relative;z-index:1;color:#fff;font-size:.8rem;
  opacity:0;transform:scale(0);transition:all .18s .06s}
.chk.on .chk-icon{opacity:1;transform:scale(1)}
.tl-item.is-late .chk{border-color:var(--rose)}
.tl-item.is-soon .chk{border-color:#d4a017}
.tl-item.is-secret .chk{border-color:var(--purple)}

.item-body{flex:1;min-width:0}
.item-time-row{display:flex;align-items:center;gap:5px;margin-bottom:2px}
.itime{font-size:.68rem;font-weight:700;letter-spacing:.05em;color:var(--gold)}
.tl-item.is-done .itime{color:var(--sage)}
.tl-item.is-late .itime{color:var(--rose)}
.tl-item.is-soon .itime{color:#b8880a}
.badge-s{font-size:.58rem;font-weight:700;background:var(--purple);color:#fff;
  border-radius:5px;padding:1px 5px;text-transform:uppercase}
.iwhat{font-size:.88rem;font-weight:600;color:var(--ink);line-height:1.3}
.tl-item.is-done .iwhat{text-decoration:line-through;color:var(--ink-faint)}
.iwho{font-size:.7rem;color:var(--ink-soft);margin-top:2px}
.iloc{font-size:.65rem;color:var(--ink-faint);margin-top:1px}

/* countdown */
.cd{flex-shrink:0;text-align:center;min-width:44px}
.cd-n{font-size:.95rem;font-weight:700;font-variant-numeric:tabular-nums;line-height:1.1}
.cd-u{font-size:.56rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;opacity:.7}
.cd.cf{color:var(--ink-faint)}.cd.cs{color:#b8880a}.cd.cl{color:var(--rose)}.cd.ck{color:var(--sage)}

.smiley{font-size:1.25rem;flex-shrink:0;animation:popIn .35s cubic-bezier(.34,1.56,.64,1) both}
@keyframes popIn{from{transform:scale(0) rotate(-20deg);opacity:0}to{transform:scale(1) rotate(0);opacity:1}}

/* ── NEXT BANNER ── */
.next-banner{
  background:linear-gradient(100deg,var(--rose-pale),var(--gold-pale));
  border:1px solid var(--gold-light);border-radius:var(--r);
  padding:10px 13px;margin-bottom:14px;margin-left:44px;
  display:flex;align-items:center;gap:10px;
}
.nb-icon{font-size:1.15rem;flex-shrink:0}
.nb-body{flex:1;min-width:0}
.nb-lbl{font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--ink-faint)}
.nb-what{font-size:.84rem;font-weight:600;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.nb-cd{font-size:.75rem;font-weight:700;color:var(--rose);white-space:nowrap;flex-shrink:0}

/* ── CONTACTS ── */
.c-card{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);
  box-shadow:var(--sh);padding:12px 13px;display:flex;align-items:center;gap:11px;margin-bottom:9px}
.c-av{width:40px;height:40px;border-radius:50%;flex-shrink:0;font-size:.88rem;font-weight:700;
  color:#fff;display:flex;align-items:center;justify-content:center;
  background:linear-gradient(135deg,var(--gold),var(--rose))}
.c-name{font-size:.88rem;font-weight:700}
.c-role{font-size:.7rem;color:var(--rose);font-weight:600;margin-top:1px}
.c-note{font-size:.66rem;color:var(--ink-faint);margin-top:1px}
.c-call{
  flex-shrink:0;width:38px;height:38px;border-radius:50%;
  background:var(--sage-pale);border:1.5px solid var(--sage-light);
  color:var(--sage);display:flex;align-items:center;justify-content:center;
  text-decoration:none;transition:background .18s,transform .15s;
  -webkit-tap-highlight-color:transparent;
}
.c-call:active{background:var(--sage-light);transform:scale(.92)}

/* ── CORSAGES ── */
.cor-intro{background:var(--gold-pale);border:1px solid var(--gold-light);border-radius:var(--r);
  padding:11px 13px;font-size:.78rem;color:var(--ink-soft);margin-bottom:12px;line-height:1.5}
.cor-card{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);
  box-shadow:var(--sh);padding:11px 13px;display:flex;align-items:center;
  justify-content:space-between;margin-bottom:8px;transition:background .2s}
.cor-card.done{background:var(--sage-pale);border-color:var(--sage-light)}
.cor-name{font-size:.88rem;font-weight:700}
.cor-role{font-size:.7rem;color:var(--ink-soft);margin-top:1px}
.cor-btn{width:30px;height:30px;border-radius:50%;border:2px solid var(--gold);
  background:transparent;cursor:pointer;display:flex;align-items:center;justify-content:center;
  position:relative;overflow:hidden}
.cor-btn::after{content:'';position:absolute;inset:0;border-radius:50%;background:var(--sage);
  transform:scale(0);transition:transform .22s cubic-bezier(.34,1.56,.64,1)}
.cor-btn.done{border-color:var(--sage)}.cor-btn.done::after{transform:scale(1)}
.cor-btn-ic{position:relative;z-index:1;color:#fff;font-size:.8rem;opacity:0;transform:scale(0);transition:all .18s .05s}
.cor-btn.done .cor-btn-ic{opacity:1;transform:scale(1)}

/* ── OPEN PUNTEN ── */
.punt{background:var(--surface);border-radius:var(--r);border:1px solid #f5d0a0;
  border-left:4px solid #d4a017;box-shadow:var(--sh);padding:11px 13px;margin-bottom:8px;font-size:.82rem;line-height:1.5}

/* ── BOTTOM NAV ── */
.bnav{
  position:fixed;bottom:0;left:0;right:0;
  display:flex;justify-content:space-around;
  background:rgba(255,252,249,.98);
  border-top:1px solid var(--border);
  padding:8px 0 max(10px,env(safe-area-inset-bottom));
  z-index:300;box-shadow:0 -4px 20px rgba(45,36,32,.08);
}
.nb{display:flex;flex-direction:column;align-items:center;gap:2px;background:none;border:none;
  cursor:pointer;color:var(--ink-faint);font-size:.58rem;font-weight:600;font-family:'Inter',sans-serif;
  text-transform:uppercase;letter-spacing:.06em;padding:3px 12px;transition:color .2s}
.nb.active{color:var(--ink)}
.nb .ni{font-size:1.35rem;line-height:1;transition:transform .2s cubic-bezier(.34,1.56,.64,1)}
.nb.active .ni{transform:scale(1.18)}
.nb-logout{text-decoration:none;color:var(--ink-faint)}

/* ── NOTITIE KNOP ── */
.note-btn{
  position:absolute;bottom:8px;right:8px;
  width:26px;height:26px;border-radius:50%;border:none;
  background:var(--gold-pale);color:var(--gold);
  font-size:.78rem;cursor:pointer;display:flex;align-items:center;justify-content:center;
  opacity:.7;transition:opacity .15s,transform .15s;z-index:2;
  -webkit-tap-highlight-color:transparent;
}
.note-btn:active{opacity:1;transform:scale(.9)}
.note-btn.has-note{opacity:1;background:var(--gold-light)}

/* ── NOTITIE MODAL (gecentreerd) ── */
.modal-bg{position:fixed;inset:0;background:rgba(45,36,32,.55);z-index:400;
  opacity:0;pointer-events:none;transition:opacity .22s}
.modal-bg.open{opacity:1;pointer-events:all}
.modal{position:fixed;top:50%;left:50%;transform:translate(-50%,-46%) scale(.95);
  width:min(92vw,400px);background:var(--surface);border-radius:20px;
  padding:22px 20px 18px;z-index:401;
  box-shadow:0 20px 60px rgba(45,36,32,.28);
  transition:transform .25s cubic-bezier(.34,1.2,.64,1),opacity .22s;
  opacity:0;pointer-events:none;visibility:hidden}
.modal.open{transform:translate(-50%,-50%) scale(1);opacity:1;pointer-events:all;visibility:visible}
.modal-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:14px;gap:10px}
.modal-title{font-family:'Cormorant Garamond',serif;font-size:1.1rem;color:var(--ink);line-height:1.3}
.modal-close{flex-shrink:0;width:28px;height:28px;border-radius:50%;border:none;
  background:var(--bg);color:var(--ink-soft);font-size:1rem;cursor:pointer;
  display:flex;align-items:center;justify-content:center;transition:background .15s}
.modal-close:active{background:var(--border)}
textarea.modal-note{width:100%;padding:11px 13px;font-family:'Inter',sans-serif;font-size:.88rem;
  color:var(--ink);background:var(--gold-pale);border:1.5px solid var(--gold-light);
  border-radius:10px;resize:none;min-height:90px;line-height:1.6;transition:border-color .2s}
textarea.modal-note:focus{outline:none;border-color:var(--gold)}
.modal-save{width:100%;margin-top:10px;padding:12px;border-radius:10px;border:none;
  background:var(--ink);color:#f5ede0;font-family:'Inter',sans-serif;font-weight:700;
  font-size:.88rem;cursor:pointer}
.modal-save:active{opacity:.8}

/* ── CONFETTI ── */
.cf{position:fixed;pointer-events:none;z-index:9999;animation:cffall 1.1s ease-out forwards;border-radius:2px}
@keyframes cffall{0%{transform:translateY(0) rotate(0) scale(1);opacity:1}100%{transform:translateY(180px) rotate(540deg) scale(.4);opacity:0}}

/* ── TOAST ── */
.toast{position:fixed;bottom:80px;left:50%;transform:translateX(-50%) translateY(20px);opacity:0;
  background:var(--ink);color:#f5ede0;padding:8px 18px;border-radius:30px;font-size:.78rem;
  font-weight:600;transition:all .28s ease;z-index:500;white-space:nowrap;pointer-events:none}
.toast.show{transform:translateX(-50%) translateY(0);opacity:1}

/* ── VIEW SECTION HEADER ── */
.view-hdr{font-family:'Cormorant Garamond',serif;font-size:1.15rem;color:var(--ink-soft);margin-bottom:12px}

@keyframes slideIn{from{opacity:0;transform:translateX(16px)}to{opacity:1;transform:none}}
.slide-in{animation:slideIn .2s ease both}
</style>
</head>
<body>
<div id="ambientOrb"></div>

<!-- ══ HERO HEADER + PHASE STRIP (single sticky unit) ══ -->
<div class="sticky-head" id="stickyHead">
<header class="hero" id="hero">
  <div class="hero-inner">
    <div class="hero-top">
      <div>
        <div class="hero-title">Danique <em>&</em> Rens</div>
        <div class="hero-sub">8 augustus 2026 · Hoeve Zzamen</div>
      </div>
      <div style="text-align:right">
        <div id="liveClock">--:--</div>
        <div class="clock-sub">Live <span class="sync-dot" id="syncDot"></span></div>
      </div>
    </div>
  </div>
  <div class="hero-progress">
    <div class="prog-track"><div class="prog-fill" id="progFill" style="width:0%"></div></div>
    <div class="prog-label" id="progLabel">0 / 0</div>
  </div>
  <div class="ticker" id="ticker"></div>
</header>

<div class="phase-strip" id="phaseStrip"></div>
</div><!-- /sticky-head -->

<!-- DRAAIBOEK -->
<div class="content" id="viewDraaiboek">
  <div id="phaseContent"></div>
</div>

<!-- CONTACTEN -->
<div class="static-view" id="viewContacten">
  <div class="view-hdr">Leveranciers &amp; contacten</div>
  <div id="contactList"></div>
</div>

<!-- CORSAGES -->
<div class="static-view" id="viewCorsages">
  <div class="view-hdr">Corsages &amp; boeket</div>
  <div class="cor-intro">🌸 Geleverd door <strong>Het Bloemenhart</strong>.<br>Vink af zodra de corsage is overhandigd.</div>
  <div id="corsageList"></div>
</div>

<!-- OPEN PUNTEN -->
<div class="static-view" id="viewPunten">
  <div class="view-hdr">Openstaande punten</div>
  <?php foreach([
    'Locatie fotoshoot & geloftes: nader te bepalen',
    'Tijdstip aflevering trouwauto door Marcus (moet vóór 12:00 aanwezig zijn)',
    'Tijdstip aflevering bruidsboeket & corsages door Het Bloemenhart',
    'Naam feestopbouwbedrijf + aankomsttijd: nader te bepalen',
    'Eindmoment feest (last dance / uitzwaai): nader te bepalen',
    'Verantwoordelijkheden per moment: nader in te vullen',
  ] as $p) echo '<div class="punt">⚠️ '.htmlspecialchars($p).'</div>'; ?>
</div>

<nav class="bnav">
  <button class="nb active" id="nav-draaiboek" onclick="showView('draaiboek')"><span class="ni">📋</span>Draaiboek</button>
  <button class="nb" id="nav-contacten" onclick="showView('contacten')"><span class="ni">👥</span>Contacten</button>
  <button class="nb" id="nav-corsages" onclick="showView('corsages')"><span class="ni">🌸</span>Corsages</button>
  <button class="nb" id="nav-punten" onclick="showView('punten')"><span class="ni">⚠️</span>Openstaand</button>
  <a class="nb nb-logout" href="index.php?logout=1" title="Uitloggen"><span class="ni">🔒</span>Uit</a>
</nav>

<div class="modal-bg" id="modalBg" onclick="closeSheet()"></div>
<div class="modal" id="modal">
  <div class="modal-header">
    <div class="modal-title" id="sheetTitle">Notitie</div>
    <button class="modal-close" onclick="closeSheet()">✕</button>
  </div>
  <textarea class="modal-note" id="sheetNote" placeholder="Voeg een notitie toe…"></textarea>
  <button class="modal-save" onclick="saveSheetNote()">Opslaan</button>
</div>
<div class="toast" id="toast"></div>

<script>
const D = <?= $initData ?>;
let lastChangeId = D.last_change_id;
let activeView = 'draaiboek';
let activePhaseIdx = 0;
let sheetItemId = null;

const CONTACTS = [
  {av:'KJ',name:'Kelly & Jordi',role:'Fotografen',note:'Aanwezig vanaf 08:00 hotel · gehele dag',tel:'+31628620796'},
  {av:'ML',name:'Mirjam & Lianne',role:'Make-up artists',note:'Aanwezig 07:30–12:15 hotel'},
  {av:'SU',name:'Sulaika',role:'BABS',note:'Aanwezig 14:30–ca. 16:30',tel:'+31620777758'},
  {av:'DL',name:'Daniëlle',role:'Ceremoniemeester',note:'Aanwezig vanaf 13:00',tel:'+31616531619'},
  {av:'MA',name:'Marcus',role:'Vader bruid · trouwauto',note:'Aflevering vóór 12:00 — tijdstip NTB',tel:'+31610082926'},
  {av:'🌸',name:'Het Bloemenhart',role:'Bloemist',note:'Aflevering boeket + corsages · tijdstip NTB',tel:'+31107371611'},
  {av:'TS',name:'Thomas & Sjoerd',role:'DJ & Saxofonist',note:'Aankomst ca. 19:30',tel:'+31646001296'},
  {av:'🎺',name:'Passion Music Brass',role:'Brassband · Verrassing voor gasten!',note:'Aankomst 19:30 via melkhuisje · opkomst 20:15',tel:'+31616538147'},
  {av:'🏗️',name:'Feestopbouwbedrijf',role:'Opbouw feestzaal',note:'Naam + tijdstip NTB'},
];

/* ── TIME UTILS ── */
function toMs(t,date){
  if(!t)return null;
  const[h,m]=t.split(':').map(Number),d=new Date(date??'2026-08-09');
  d.setHours(h,m,0,0);return d.getTime();
}
function cd(ms){
  const min=Math.round((ms-Date.now())/60000),abs=Math.abs(min);
  const days=Math.floor(abs/1440),h=Math.floor((abs%1440)/60),m=abs%60;
  let val;
  if(days>=7)val=Math.floor(days/7)+'w'+(days%7?days%7+'d':'');
  else if(days>=1)val=days+'d'+(h>0?h+'u':'');
  else if(h>0)val=h+'u'+(m>0?m+'m':'');
  else val=m+'m';
  return{val,late:min<0,soon:min>=0&&min<=30,unit:min>=0?'nog':'te laat'};
}

/* ── FASE-THEMA ── */
const PHASE_THEMES = {
  vrijdag:   {accent:'#a78bdb', dark:'#2a1a50', bg:'#f0ecf8', line:'#a78bdb', chk:'#a78bdb'},
  ochtend:   {accent:'#c9a96e', dark:'#3a2414', bg:'#f2e8dc', line:'#c9a96e', chk:'#c9a96e'},
  fotoshoot: {accent:'#c4a0d8', dark:'#3e1e58', bg:'#f7f2fb', line:'#b888cc', chk:'#b888cc'},
  opbouw:    {accent:'#7aaa7e', dark:'#1e402a', bg:'#edf5ee', line:'#7aaa7e', chk:'#7aaa7e'},
  programma: {accent:'#c8737a', dark:'#5a2028', bg:'#fdf0f1', line:'#c8737a', chk:'#c8737a'},
};
function applyPhaseTheme(phaseId){
  const t=PHASE_THEMES[phaseId]||PHASE_THEMES.ochtend;
  const r=document.documentElement.style;
  r.setProperty('--phase-accent',t.accent);
  r.setProperty('--phase-dark',t.dark);
  r.setProperty('--phase-line',t.line);
  document.body.style.backgroundColor=t.bg;
  // header gradient
  document.getElementById('hero').style.background=
    `linear-gradient(160deg,${t.dark} 0%,${t.accent}33 55%,${t.dark} 100%)`;
}

/* ── AUTO-PHASE ── */
function autoPhase(){
  const now=Date.now();let best=-1,bestDiff=Infinity;
  D.phases.forEach((ph,idx)=>{
    D.items.filter(i=>i.phase_id===ph.id&&i.time_start&&!+i.is_done).forEach(it=>{
      const ms=toMs(it.time_start,ph.date),diff=ms-now;
      if(diff>-3600000&&Math.abs(diff)<bestDiff){bestDiff=Math.abs(diff);best=idx;}
    });
  });
  return best>=0?best:0;
}

/* ── ACTIVE SET: first 5 unchecked ── */
function activeSet(items){
  return new Set(items.filter(i=>!+i.is_done).slice(0,5).map(i=>i.id));
}

/* ── SMILEY ── */
function smiley(item){
  if(!+item.is_done||!item.time_start)return '';
  if(item.on_time===null||item.on_time===undefined||item.on_time==='')return '';
  return+item.on_time?`<span class="smiley">😊</span>`:`<span class="smiley">😠</span>`;
}

/* ── RENDER STRIP ── */
function renderStrip(){
  const strip=document.getElementById('phaseStrip');
  strip.innerHTML=D.phases.map((p,i)=>{
    const its=D.items.filter(x=>x.phase_id===p.id);
    const done=its.filter(x=>+x.is_done).length;
    const hasAlert=its.some(x=>{
      if(+x.is_done||!x.time_start)return false;
      const r=cd(toMs(x.time_start,x.date??p.date));
      return r.late||r.soon;
    });
    return`<button class="phase-pill${i===activePhaseIdx?' active':''}${hasAlert?' has-alert':''}" onclick="goPhase(${i})">
      ${p.emoji} ${p.label}<span class="pc">${done}/${its.length}</span></button>`;
  }).join('');
  const pill=strip.querySelectorAll('.phase-pill')[activePhaseIdx];
  if(pill)pill.scrollIntoView({behavior:'smooth',block:'nearest',inline:'center'});
}

/* ── RENDER PHASE as TIMELINE ── */
function renderPhase(){
  const ph=D.phases[activePhaseIdx];
  if(!ph)return;
  const items=D.items.filter(i=>i.phase_id===ph.id);
  const now=Date.now();

  // Next-up banner
  let nextItem=null,nextMs=null;
  for(const p of D.phases){
    for(const it of D.items.filter(i=>i.phase_id===p.id)){
      if(+it.is_done||!it.time_start)continue;
      const ms=toMs(it.time_start,p.date);
      if(ms>now){nextItem=it;nextMs=ms;break;}
    }
    if(nextItem)break;
  }
  let banner='';
  if(nextItem){
    const r=cd(nextMs);
    banner=`<div class="next-banner"><div class="nb-icon">⏭️</div>
      <div class="nb-body"><div class="nb-lbl">Volgende</div>
      <div class="nb-what">${esc(nextItem.what)}</div></div>
      <div class="nb-cd">${r.val} ${r.unit}</div></div>`;
  }

  const active=activeSet(items);
  // Spotlight: eerste unchecked item
  const spotlightId=items.find(i=>!+i.is_done)?.id??null;

  const cards=items.map((item,idx)=>{
    const done=+item.is_done;
    const queued=!done&&!active.has(item.id);
    const ms=item.time_start?toMs(item.time_start,ph.date):null;
    const timeLbl=item.time_start?(item.time_end?`${item.time_start}–${item.time_end}`:item.time_start):'';
    let cls='',rightSlot='';

    if(done){
      cls='is-done';rightSlot=smiley(item);
    }else if(!queued&&ms!==null){
      const r=cd(ms);
      cls=r.late?'is-late':r.soon?'is-soon':'';
      if(+item.is_secret&&!cls)cls='is-secret';
      rightSlot=`<div class="cd ${r.late?'cl':r.soon?'cs':'cf'}"><div class="cd-n">${r.val}</div><div class="cd-u">${r.unit}</div></div>`;
    }else if(!queued&&+item.is_secret){
      cls='is-secret';
    }else if(queued&&ms!==null){
      const r=cd(ms);
      rightSlot=`<div class="cd cf" style="opacity:.4"><div class="cd-n">${r.val}</div><div class="cd-u">${r.unit}</div></div>`;
    }

    const hasNote=!!(item.note&&item.note.trim());
    const chkClick=queued?'event.stopPropagation()':`toggleDone(event,'${item.id}')`;
    const noteBtn=!queued?`<button class="note-btn${hasNote?' has-note':''}" onclick="event.stopPropagation();openSheet('${item.id}')" title="Notitie">✏️</button>`:'';

    const isSpotlight=!done&&!queued&&item.id===spotlightId;
    const delay=idx*48;
    return`<div class="tl-item entering${cls?' '+cls:''}${queued?' queued':''}${isSpotlight?' spotlight':''}" id="card-${item.id}" style="animation-delay:${delay}ms">
      <div class="tl-dot"></div>
      <div class="swipe-wrap">
        ${!done&&!queued?`<div class="swipe-bg swipe-bg-r">✓</div><div class="swipe-bg swipe-bg-l">✏️</div>`:''}
        <div class="tl-card" style="position:relative">
          <div class="tl-row">
            <button class="chk ${done?'on':''}" onclick="${chkClick}" ${queued?'disabled':''}>
              <span class="chk-icon">✓</span>
            </button>
            <div class="item-body">
              ${timeLbl||+item.is_secret?`<div class="item-time-row">
                ${timeLbl?`<span class="itime">${timeLbl}</span>`:''}
                ${+item.is_secret?`<span class="badge-s">🎺 Verrassing</span>`:''}
              </div>`:''}
              <div class="iwhat">${esc(item.what)}</div>
              <div class="iwho">${esc(item.who)}</div>
              ${item.location?`<div class="iloc">📍 ${esc(item.location)}</div>`:''}
              ${hasNote?`<div style="font-size:.65rem;color:var(--gold);margin-top:3px">📝 ${esc(item.note.split('\n')[0]).substring(0,50)}</div>`:''}
            </div>
            ${rightSlot}
          </div>
          ${noteBtn}
        </div>
      </div>
    </div>`;
  }).join('');

  document.getElementById('phaseContent').innerHTML=
    `<div class="tl-phase-label">${ph.emoji} ${ph.label}</div>
     ${banner}
     <div class="timeline" id="timeline">${cards}</div>`;
  // Trigger zelf-tekenende lijn na volgende frame
  requestAnimationFrame(()=>requestAnimationFrame(()=>{
    const tl=document.getElementById('timeline');
    if(tl)tl.classList.add('drawn');
  }));
}

/* ── NAVIGATION ── */
function goPhase(idx){
  activePhaseIdx=idx;
  applyPhaseTheme(D.phases[idx]?.id);
  renderStrip();renderPhase();
  window.scrollTo({top:0,behavior:'instant'});
}

function showView(v){
  activeView=v;
  document.getElementById('viewDraaiboek').style.display   = v==='draaiboek'?'block':'none';
  document.getElementById('phaseStrip').style.display      = v==='draaiboek'?'flex':'none';
  document.getElementById('viewContacten').style.display   = v==='contacten'?'block':'none';
  document.getElementById('viewCorsages').style.display    = v==='corsages'?'block':'none';
  document.getElementById('viewPunten').style.display      = v==='punten'?'block':'none';
  document.querySelectorAll('.nb').forEach(b=>b.classList.remove('active'));
  document.getElementById('nav-'+v).classList.add('active');
  if(v==='contacten')renderContacts();
  if(v==='corsages')renderCorsages();
  window.scrollTo({top:0,behavior:'smooth'});
}

/* ── SWIPE between phases ── */
let _sx=0,_sy=0,_dragging=false,_dx=0;
document.addEventListener('touchstart',e=>{
  if(activeView!=='draaiboek')return;
  _sx=e.touches[0].clientX;_sy=e.touches[0].clientY;_dragging=true;_dx=0;
},{passive:true});
document.addEventListener('touchmove',e=>{
  if(!_dragging)return;
  _dx=e.touches[0].clientX-_sx;
  const dy=e.touches[0].clientY-_sy;
  if(Math.abs(dy)>Math.abs(_dx)+10)_dragging=false;
},{passive:true});
let _cardSwiping=false;
document.addEventListener('touchend',()=>{
  if(!_dragging||_cardSwiping){_dx=0;_dragging=false;return;}
  _dragging=false;
  if(_dx<-60&&activePhaseIdx<D.phases.length-1)goPhase(activePhaseIdx+1);
  else if(_dx>60&&activePhaseIdx>0)goPhase(activePhaseIdx-1);
  _dx=0;
});

/* ── GLASSMORPHISM HEADER SHRINK ── */
(function(){
  const hero=document.getElementById('hero');
  let compact=false;
  window.addEventListener('scroll',()=>{
    const shouldCompact=window.scrollY>60;
    if(shouldCompact!==compact){
      compact=shouldCompact;
      hero.classList.toggle('compact',compact);
    }
  },{passive:true});
})();

/* ── INTERACTIONS ── */
async function toggleDone(e,id){
  e.stopPropagation();
  const item=D.items.find(i=>i.id===id);
  if(!item)return;
  item.is_done=item.is_done?0:1;
  let onTime=null;
  if(item.is_done&&item.time_start){
    const ph=D.phases.find(p=>p.id===item.phase_id);
    const ms=toMs(item.time_start,ph?.date??'2026-08-09');
    onTime=Date.now()<=ms?1:0;
    item.on_time=onTime;
  }else{item.on_time=null;}
  if(item.is_done){spawnConfetti(e);vibrate();showToast(onTime===1?'Op tijd! 😊':onTime===0?'Te laat 😠':'Afgevinkt ✓');}
  renderPhase();renderStrip();updateProgress();
  await api('toggle_item',{id,done:item.is_done,on_time:onTime});
}

async function toggleCorsage(id){
  const c=D.corsages.find(x=>x.id===id);if(!c)return;
  c.is_done=c.is_done?0:1;renderCorsages();
  await api('toggle_corsage',{id,done:c.is_done});
}

/* ── MODAL ── */
function openSheet(id){
  const item=D.items.find(i=>i.id===id);if(!item)return;
  sheetItemId=id;
  document.getElementById('sheetTitle').textContent=item.what.substring(0,50);
  document.getElementById('sheetNote').value=item.note||'';
  document.getElementById('modalBg').classList.add('open');
  document.getElementById('modal').classList.add('open');
  setTimeout(()=>document.getElementById('sheetNote').focus(),250);
}
function closeSheet(){
  document.getElementById('modalBg').classList.remove('open');
  document.getElementById('modal').classList.remove('open');
}
async function saveSheetNote(){
  const note=document.getElementById('sheetNote').value;
  const item=D.items.find(i=>i.id===sheetItemId);
  if(item)item.note=note;
  closeSheet();renderPhase();
  await api('save_note',{id:sheetItemId,note});
  showToast('Notitie opgeslagen ✓');
}

/* ── RENDERS ── */
function renderContacts(){
  document.getElementById('contactList').innerHTML=CONTACTS.map(c=>
    `<div class="c-card">
      <div class="c-av">${c.av}</div>
      <div style="flex:1;min-width:0">
        <div class="c-name">${c.name}</div>
        <div class="c-role">${c.role}</div>
        <div class="c-note">${c.note}</div>
      </div>
      ${c.tel?`<a href="tel:${c.tel}" class="c-call" title="Bellen">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.44 2 2 0 0 1 3.6 1.27h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L7.91 8.82a16 16 0 0 0 6.08 6.08l1.68-1.68a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
        </svg>
      </a>`:''}
    </div>`
  ).join('');
}
function renderCorsages(){
  document.getElementById('corsageList').innerHTML=D.corsages.map(c=>{
    const done=+c.is_done;
    return`<div class="cor-card${done?' done':''}" id="cor-${c.id}">
      <div><div class="cor-name">${esc(c.name)}</div><div class="cor-role">${esc(c.role)}</div></div>
      <button class="cor-btn${done?' done':''}" onclick="toggleCorsage('${c.id}')">
        <span class="cor-btn-ic">✓</span></button></div>`;
  }).join('');
}

function updateProgress(){
  const total=D.items.length,done=D.items.filter(i=>+i.is_done).length;
  const pct=total?(done/total*100).toFixed(0):0;
  document.getElementById('progFill').style.width=pct+'%';
  document.getElementById('progLabel').textContent=`${done} / ${total}`;
}

/* ── POLLING ── */
const dot=document.getElementById('syncDot');
async function poll(){
  try{
    const r=await fetch(`api.php?action=poll&last_id=${lastChangeId}`);
    if(!r.ok)throw new Error();
    const j=await r.json();
    dot.classList.remove('err');
    if(j.changes&&j.changes.length){lastChangeId=j.last_id;j.changes.forEach(applyChange);}
  }catch{dot.classList.add('err');}
}
function applyChange(ev){
  const{action:a,payload:p}=ev;
  if(a==='toggle_item'){const it=D.items.find(i=>i.id===p.id);if(it){it.is_done=p.done;it.on_time=p.on_time;renderPhase();renderStrip();updateProgress();}}
  if(a==='save_note'){const it=D.items.find(i=>i.id===p.id);if(it){it.note=p.note;renderPhase();}}
  if(a==='toggle_corsage'){const c=D.corsages.find(x=>x.id===p.id);if(c){c.is_done=p.done;if(activeView==='corsages')renderCorsages();}}
  if(a.startsWith('admin_')){fetch('api.php?action=get_all').then(r=>r.json()).then(d=>{Object.assign(D,d);lastChangeId=d.last_change_id;renderPhase();renderStrip();updateProgress();});showToast('Draaiboek bijgewerkt');}
}
setInterval(poll,15000);

/* ── API ── */
async function api(action,payload){
  try{await fetch('api.php?action='+action,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action,...payload})});}catch{}
}

/* ── CONFETTI ── */
const CFC=['#c8737a','#c9a96e','#7a9e7e','#d4b0e0','#f5c0a0'];
function spawnConfetti(e){
  const x=e.clientX??window.innerWidth/2,y=e.clientY??window.innerHeight/2;
  for(let i=0;i<20;i++){
    const el=document.createElement('div');el.className='cf';
    const s=5+Math.random()*7;
    el.style.cssText=`left:${x+(Math.random()-.5)*70}px;top:${y}px;background:${CFC[~~(Math.random()*CFC.length)]};width:${s}px;height:${s}px;border-radius:${Math.random()>.4?'50%':'2px'};animation-duration:${.7+Math.random()*.7}s;animation-delay:${Math.random()*.12}s`;
    document.body.appendChild(el);el.addEventListener('animationend',()=>el.remove());
  }
}
function vibrate(){if(navigator.vibrate)navigator.vibrate(40);}

/* ── TOAST ── */
function showToast(msg){
  const t=document.getElementById('toast');t.textContent=msg;
  t.classList.add('show');setTimeout(()=>t.classList.remove('show'),2800);
}

function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}

/* ── SWIPE-TO-COMPLETE ── */
{
  let el=null,sid=null,sx=0,sy=0,dx=0,tracking=false;
  const pc=document.getElementById('phaseContent');

  pc.addEventListener('touchstart',e=>{
    const wrap=e.target.closest('.swipe-wrap');
    if(!wrap)return;
    const item=wrap.closest('.tl-item');
    if(!item||item.classList.contains('queued')||item.classList.contains('is-done'))return;
    el=wrap;sid=item.id.replace('card-','');
    sx=e.touches[0].clientX;sy=e.touches[0].clientY;dx=0;tracking=false;
  },{passive:true});

  pc.addEventListener('touchmove',e=>{
    if(!el)return;
    const cx=e.touches[0].clientX,cy=e.touches[0].clientY;
    dx=cx-sx;
    if(!tracking){
      if(Math.abs(dx)<7&&Math.abs(cy-sy)<7)return;
      if(Math.abs(cy-sy)>Math.abs(dx)){el=null;return;}
      tracking=true;
    }
    _cardSwiping=true;
    e.preventDefault();
    const card=el.querySelector('.tl-card');
    const capped=Math.sign(dx)*Math.min(Math.abs(dx),105);
    if(card){card.style.transition='none';card.style.transform=`translateX(${capped}px)`;}
    const br=el.querySelector('.swipe-bg-r');
    const bl=el.querySelector('.swipe-bg-l');
    if(br)br.style.opacity=dx>0?Math.min(dx/70,1):0;
    if(bl)bl.style.opacity=dx<0?Math.min(-dx/70,1):0;
  },{passive:false});

  pc.addEventListener('touchend',async()=>{
    if(!el||!tracking){el=null;_cardSwiping=false;tracking=false;return;}
    const card=el.querySelector('.tl-card');
    const br=el.querySelector('.swipe-bg-r');
    const bl=el.querySelector('.swipe-bg-l');
    const id=sid;
    if(dx>60){
      if(card){card.style.transition='transform .2s ease';card.style.transform='translateX(110%)';}
      setTimeout(async()=>{
        const fakeE={stopPropagation:()=>{},clientX:sx+50,clientY:sy};
        await toggleDone(fakeE,id);
      },180);
    }else if(dx<-60){
      if(card){card.style.transition='transform .18s ease';card.style.transform='translateX(-110%)';}
      setTimeout(()=>openSheet(id),120);
      setTimeout(()=>{
        if(card){card.style.transition='transform .3s cubic-bezier(.34,1.56,.64,1)';card.style.transform='';}
        if(br)br.style.opacity=0;if(bl)bl.style.opacity=0;
      },380);
    }else{
      if(card){card.style.transition='transform .32s cubic-bezier(.34,1.56,.64,1)';card.style.transform='';}
      if(br)br.style.opacity=0;if(bl)bl.style.opacity=0;
    }
    el=null;tracking=false;
    setTimeout(()=>{_cardSwiping=false;},320);
  },{passive:true});
}

/* ── AMBIENT DAG-KLEUREN ── */
const AMBIENT_STOPS=[
  {h:0, c:'#3a1860'},{h:6, c:'#f09050'},{h:8, c:'#f8c870'},
  {h:12,c:'#ffe898'},{h:15,c:'#f4b880'},{h:17,c:'#e87050'},
  {h:19,c:'#c06070'},{h:21,c:'#803898'},{h:23,c:'#3a1060'}
];
function lerpHex(a,b,t){
  const h=s=>parseInt(s,16);
  const r=Math.round(h(a.slice(1,3))+(h(b.slice(1,3))-h(a.slice(1,3)))*t);
  const g=Math.round(h(a.slice(3,5))+(h(b.slice(3,5))-h(a.slice(3,5)))*t);
  const bl=Math.round(h(a.slice(5,7))+(h(b.slice(5,7))-h(a.slice(5,7)))*t);
  return`#${r.toString(16).padStart(2,'0')}${g.toString(16).padStart(2,'0')}${bl.toString(16).padStart(2,'0')}`;
}
function ambientTick(){
  const orb=document.getElementById('ambientOrb');if(!orb)return;
  const now=new Date(),nowMin=now.getHours()*60+now.getMinutes();
  let prev=AMBIENT_STOPS[AMBIENT_STOPS.length-1],next=AMBIENT_STOPS[0];
  for(let i=0;i<AMBIENT_STOPS.length-1;i++){
    if(AMBIENT_STOPS[i].h*60<=nowMin&&AMBIENT_STOPS[i+1].h*60>nowMin){
      prev=AMBIENT_STOPS[i];next=AMBIENT_STOPS[i+1];break;
    }
  }
  const pm=prev.h*60,nm=next.h*60<=pm?next.h*60+1440:next.h*60;
  const t=Math.max(0,Math.min(1,(nowMin-pm)/(nm-pm)));
  const color=lerpHex(prev.c,next.c,t);
  orb.style.background=`radial-gradient(circle,${color} 0%,transparent 70%)`;
}

/* ── LIVE TICKER ── */
function tickerTick(){
  const ticker=document.getElementById('ticker');
  if(!ticker||activeView!=='draaiboek'){return;}
  const now=Date.now();
  let best=null,bestMs=Infinity;
  for(const ph of D.phases){
    for(const it of D.items.filter(i=>i.phase_id===ph.id)){
      if(+it.is_done||!it.time_start)continue;
      const ms=toMs(it.time_start,it.date??ph.date);
      if(ms>now&&ms<bestMs){bestMs=ms;best=it;}
    }
  }
  if(!best){ticker.innerHTML='';return;}
  const diff=bestMs-now;
  const urgent=diff<5*60*1000;
  const h=Math.floor(diff/3600000);
  const m=Math.floor((diff%3600000)/60000);
  const s=Math.floor((diff%60000)/1000);
  const cdStr=h>0
    ?`${h}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`
    :`${m}:${String(s).padStart(2,'0')}`;
  ticker.innerHTML=`<div class="ticker-inner">
    <div class="ticker-dot${urgent?' urgent':''}"></div>
    <div class="ticker-what">${esc(best.what)}</div>
    <div class="ticker-cd${urgent?' urgent':''}">nog ${cdStr}</div>
  </div>`;
}

/* ── CLOCK ── */
function tick(){
  const n=new Date();
  document.getElementById('liveClock').textContent=
    String(n.getHours()).padStart(2,'0')+':'+String(n.getMinutes()).padStart(2,'0');
  if(activeView==='draaiboek'){renderPhase();updateProgress();}
}

/* ── INIT ── */
activePhaseIdx=Math.max(0,D.phases.findIndex(p=>p.id==='vrijdag'));
applyPhaseTheme(D.phases[activePhaseIdx]?.id);
renderStrip();renderPhase();updateProgress();tick();
ambientTick();tickerTick();
setInterval(tick,30000);
setInterval(tickerTick,1000);
setInterval(ambientTick,60000);
poll();
</script>
</body>
</html>
