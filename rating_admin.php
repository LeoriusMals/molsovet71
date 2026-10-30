<?php
declare(strict_types=1);
session_start();
function esc($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function json_store_path(): string { $p=getenv('PORTAL_JSON_PATH'); return $p ?: dirname(__DIR__).DIRECTORY_SEPARATOR.'molodezhny_sovet_private'.DIRECTORY_SEPARATOR.'data.json'; }
$path=json_store_path();
$data=is_file($path) ? json_decode((string)file_get_contents($path), true) : null;
$user=null;
if (is_array($data) && isset($_SESSION['user_id'])) {
  foreach (($data['users'] ?? []) as $candidate) {
    if ((int)($candidate['id'] ?? 0)===(int)$_SESSION['user_id'] && !empty($candidate['active'])) { $user=$candidate; break; }
  }
}
if (!$user) { header('Location: login.html'); exit; }
if (($user['role'] ?? '') !== 'Администратор') { http_response_code(403); exit('Доступ разрешён только администратору.'); }
?><!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Рабочее место администратора — рейтинг</title>
<style>
:root{--ink:#172b4d;--muted:#667085;--line:#e3eaf3;--blue:#155eef;--bg:#f3f6fb}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.5 Arial,sans-serif}.top{background:#102b54;color:#fff;padding:22px max(18px,calc((100% - 1280px)/2));display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}.brand{font-size:20px;font-weight:800}.sub{color:#d2def0;font-size:13px}.top a{color:white;text-decoration:none;border:1px solid #7188a9;border-radius:9px;padding:9px 13px}main{max-width:1280px;margin:24px auto;padding:0 18px}.heading{display:flex;align-items:flex-end;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:18px}h1{font-size:28px;line-height:1.2;margin:0 0 6px}p{margin:0;color:var(--muted)}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin-bottom:18px}.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:20px;min-width:0}.card h2{font-size:17px;margin:0 0 8px}.action{display:inline-flex;margin-top:14px;background:var(--blue);color:#fff;text-decoration:none;border-radius:9px;padding:11px 15px;font-weight:700}.action.secondary{background:#edf2f9;color:#233b5e}.rating-wrap{background:#fff;border:1px solid var(--line);border-radius:16px;overflow:hidden}.rating-head{padding:18px 20px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}.rating-head h2{margin:0;font-size:18px}.rating-frame{display:block;width:100%;height:calc(100vh - 260px);min-height:680px;border:0;background:white}.note{font-size:12px;color:var(--muted);margin-top:10px}@media(max-width:700px){.grid{grid-template-columns:1fr}.rating-frame{height:78vh;min-height:600px}h1{font-size:24px}}
</style></head><body>
<header class="top"><div><div class="brand">Молодёжный совет · Администрирование</div><div class="sub">Рабочее место администратора рейтинга</div></div><a href="portal.php">← Вернуться в кабинет</a></header>
<main><div class="heading"><div><h1>Рейтинг участников</h1><p>Публичная таблица встроена ниже без изменения исходного rating.html.</p></div></div>
<div class="grid"><section class="card"><h2>Управление рейтингом</h2><p>Инструменты из версии 8: участники, начисления, история действий, импорт и экспорт таблицы.</p><a class="action" href="rating_management.html">Открыть инструменты управления</a><div class="note">Инструменты находятся в отдельной копии страницы; публичный рейтинг ниже остаётся оригинальным.</div></section><section class="card"><h2>Публичный рейтинг</h2><p>Открыть рейтинг в отдельной вкладке, если нужно сравнить его с рабочим местом администратора.</p><a class="action secondary" href="rating.html" target="_blank" rel="noopener">Открыть публичный рейтинг</a></section></div>
<section class="rating-wrap"><div class="rating-head"><h2>Обычный рейтинг</h2><span class="sub" style="color:#667085">Источник: rating.html</span></div><iframe class="rating-frame" src="rating.html" title="Публичный рейтинг участников"></iframe></section></main></body></html>
