<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = getDB();

$partidas = $pdo->query("
    SELECT p.*, pe.nombre AS personaje_nombre, pe.dificultad
    FROM partidas p
    JOIN personajes pe ON pe.id = p.personaje_id
    ORDER BY p.jugado_en DESC
    LIMIT 100
")->fetchAll();

$resumen = $pdo->query("
    SELECT jugador,
           COUNT(*) AS partidas,
           SUM(puntos) AS total_pts,
           SUM(adivinado) AS aciertos,
           ROUND(AVG(pistas_usadas),1) AS prom_pistas
    FROM partidas
    GROUP BY jugador
    ORDER BY total_pts DESC
    LIMIT 20
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Historial — ¿Quién Soy?</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#F7F0E4;--cafe:#3A1F08;--cafe2:#5C3614;--ambar:#C87820;--dorado:#E8A830;--verde:#2A5A1A;--rojo:#7A1A10;--gris:#8A7A6A;--blanco:#FFFBF0;--radio:10px}
body{background:var(--bg);font-family:'DM Sans',sans-serif;color:var(--cafe);min-height:100vh}
nav{background:var(--cafe);padding:.8rem 2rem;display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap}
nav h1{font-family:'Playfair Display',serif;font-size:1.2rem;color:#FFFBF0;font-style:italic}
nav a{color:rgba(255,251,240,.6);font-size:.85rem;transition:color .2s;text-decoration:none}
nav a:hover,nav a.activa{color:var(--dorado)}
.contenedor{max-width:1100px;margin:0 auto;padding:2rem 1.5rem;display:grid;grid-template-columns:1fr 1fr;gap:2rem}
@media(max-width:800px){.contenedor{grid-template-columns:1fr}}
.card{background:var(--blanco);border-radius:var(--radio);border:1px solid rgba(92,54,20,.1);overflow:hidden}
.card-titulo{padding:1rem 1.25rem;font-family:'Playfair Display',serif;font-size:1.05rem;border-bottom:1px solid rgba(92,54,20,.08);font-weight:700}
table{width:100%;border-collapse:collapse}
th,td{padding:.65rem 1rem;text-align:left;font-size:.82rem;border-bottom:1px solid rgba(92,54,20,.06)}
th{font-size:.7rem;font-weight:600;color:var(--gris);text-transform:uppercase;letter-spacing:.05em;background:rgba(92,54,20,.03)}
tr:last-child td{border-bottom:none}
.pts{color:var(--ambar);font-weight:700}
.ok{color:var(--verde);font-weight:600}
.fallo{color:var(--rojo)}
.badge-d{font-size:.7rem;padding:2px 7px;border-radius:20px;font-weight:600;display:inline-block}
.b-facil{background:rgba(42,90,26,.08);color:var(--verde)}
.b-medio{background:rgba(200,120,32,.1);color:var(--ambar)}
.b-dificil{background:rgba(122,26,16,.1);color:var(--rojo)}
</style>
</head>
<body>
<nav>
  <h1>Admin — ¿Quién Soy?</h1>
  <a href="index.php">Personajes</a>
  <a href="historial.php" class="activa">Historial</a>
  <a href="../index.php">← Volver al juego</a>
</nav>

<div class="contenedor">
  <div class="card">
    <div class="card-titulo">🏆 Ranking de jugadores</div>
    <table>
      <thead><tr><th>#</th><th>Jugador</th><th>Pts</th><th>Aciertos</th><th>Pistas prom.</th></tr></thead>
      <tbody>
      <?php foreach ($resumen as $i => $r): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td><strong><?= htmlspecialchars($r['jugador']) ?></strong></td>
        <td class="pts"><?= $r['total_pts'] ?></td>
        <td class="ok"><?= $r['aciertos'] ?> / <?= $r['partidas'] ?></td>
        <td><?= $r['prom_pistas'] ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$resumen): ?><tr><td colspan="5" style="text-align:center;color:var(--gris);padding:2rem">Sin partidas aún</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <div class="card-titulo">📋 Últimas 100 partidas</div>
    <table>
      <thead><tr><th>Jugador</th><th>Personaje</th><th>Pistas</th><th>Pts</th><th>Resultado</th></tr></thead>
      <tbody>
      <?php foreach ($partidas as $p): ?>
      <tr>
        <td><?= htmlspecialchars($p['jugador']) ?></td>
        <td>
          <?= htmlspecialchars($p['personaje_nombre']) ?>
          <span class="badge-d b-<?= $p['dificultad'] ?>"><?= $p['dificultad'] ?></span>
        </td>
        <td><?= $p['pistas_usadas'] ?></td>
        <td class="pts"><?= $p['puntos'] ?></td>
        <td><?= $p['adivinado'] ? '<span class="ok">✓ Adivinó</span>' : '<span class="fallo">✗ No adivinó</span>' ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$partidas): ?><tr><td colspan="5" style="text-align:center;color:var(--gris);padding:2rem">Sin partidas aún</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
