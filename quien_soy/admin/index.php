<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
$pdo = getDB();

$msg = '';

// ── Acciones ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar_personaje') {
        $nombre     = trim($_POST['nombre'] ?? '');
        $testamento = $_POST['testamento'] ?? 'antiguo';
        $dificultad = $_POST['dificultad'] ?? 'medio';
        $pistas     = array_filter(array_map('trim', $_POST['pistas'] ?? []));

        if (!$nombre || count($pistas) < 1) {
            $msg = ['tipo' => 'error', 'texto' => 'El nombre y al menos 1 pista son obligatorios.'];
        } else {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $pdo->prepare("UPDATE personajes SET nombre=?, testamento=?, dificultad=? WHERE id=?")
                    ->execute([$nombre, $testamento, $dificultad, $id]);
                $pdo->prepare("DELETE FROM pistas WHERE personaje_id=?")->execute([$id]);
            } else {
                $pdo->prepare("INSERT INTO personajes (nombre, testamento, dificultad) VALUES (?,?,?)")
                    ->execute([$nombre, $testamento, $dificultad]);
                $id = (int)$pdo->lastInsertId();
            }
            $stmt = $pdo->prepare("INSERT INTO pistas (personaje_id, orden, texto) VALUES (?,?,?)");
            foreach (array_values($pistas) as $i => $texto) {
                $stmt->execute([$id, $i + 1, $texto]);
            }
            $msg = ['tipo' => 'ok', 'texto' => 'Personaje guardado correctamente.'];
        }
    }

    if ($accion === 'toggle_activo') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE personajes SET activo = NOT activo WHERE id=?")->execute([$id]);
        header('Location: index.php'); exit;
    }

    if ($accion === 'eliminar') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM personajes WHERE id=?")->execute([$id]);
        $msg = ['tipo' => 'ok', 'texto' => 'Personaje eliminado.'];
    }
}

// ── Cargar datos ───────────────────────────────
$personajes = $pdo->query("
    SELECT p.*, COUNT(pi.id) AS num_pistas
    FROM personajes p
    LEFT JOIN pistas pi ON pi.personaje_id = p.id
    GROUP BY p.id ORDER BY p.id DESC
")->fetchAll();

$editar_id = (int)($_GET['editar'] ?? 0);
$editar = null;
if ($editar_id > 0) {
    $editar = $pdo->prepare("SELECT * FROM personajes WHERE id=?");
    $editar->execute([$editar_id]);
    $editar = $editar->fetch();
    if ($editar) {
        $editar['pistas'] = $pdo->prepare("SELECT texto FROM pistas WHERE personaje_id=? ORDER BY orden");
        $editar['pistas']->execute([$editar_id]);
        $editar['pistas'] = $editar['pistas']->fetchAll(PDO::FETCH_COLUMN);
    }
}

// Estadísticas
$stats = $pdo->query("
    SELECT COUNT(*) total,
           SUM(activo) activos,
           SUM(testamento='antiguo') antiguo,
           SUM(testamento='nuevo') nuevo
    FROM personajes
")->fetch();
$total_partidas = $pdo->query("SELECT COUNT(*) FROM partidas")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — ¿Quién Soy?</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#F7F0E4;--bg2:#EFE5CC;--cafe:#3A1F08;--cafe2:#5C3614;
  --ambar:#C87820;--dorado:#E8A830;--verde:#2A5A1A;--rojo:#7A1A10;
  --gris:#8A7A6A;--blanco:#FFFBF0;--radio:10px;
}
body{background:var(--bg);font-family:'DM Sans',sans-serif;color:var(--cafe);min-height:100vh}
a{color:var(--ambar);text-decoration:none}a:hover{text-decoration:underline}

/* Nav */
nav{background:var(--cafe);padding:.8rem 2rem;display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap}
nav h1{font-family:'Playfair Display',serif;font-size:1.2rem;color:#FFFBF0;font-style:italic}
nav a{color:rgba(255,251,240,.6);font-size:.85rem;transition:color .2s}
nav a:hover,nav a.activa{color:var(--dorado);text-decoration:none}

/* Layout */
.contenedor{max-width:1100px;margin:0 auto;padding:2rem 1.5rem;display:grid;grid-template-columns:1fr 380px;gap:2rem;align-items:start}
@media(max-width:900px){.contenedor{grid-template-columns:1fr}}

/* Stats */
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:.8rem;margin-bottom:1.5rem}
.stat-box{background:var(--blanco);border-radius:var(--radio);padding:1rem;border:1px solid rgba(92,54,20,.1);text-align:center}
.stat-num{font-size:2rem;font-weight:700;color:var(--ambar);line-height:1}
.stat-lab{font-size:.72rem;color:var(--gris);margin-top:.2rem;text-transform:uppercase;letter-spacing:.05em}

/* Tabla */
.card{background:var(--blanco);border-radius:var(--radio);border:1px solid rgba(92,54,20,.1);overflow:hidden;box-shadow:0 2px 12px rgba(92,54,20,.08)}
.card-titulo{padding:1rem 1.25rem;font-family:'Playfair Display',serif;font-size:1.1rem;border-bottom:1px solid rgba(92,54,20,.08);font-weight:700}

table{width:100%;border-collapse:collapse}
th,td{padding:.7rem 1rem;text-align:left;font-size:.85rem;border-bottom:1px solid rgba(92,54,20,.06)}
th{font-size:.72rem;font-weight:600;color:var(--gris);text-transform:uppercase;letter-spacing:.05em;background:rgba(92,54,20,.03)}
tr:hover td{background:rgba(200,120,32,.04)}
tr:last-child td{border-bottom:none}

/* Badges */
.badge{display:inline-block;font-size:.7rem;font-weight:600;padding:2px 8px;border-radius:20px;letter-spacing:.04em;text-transform:capitalize}
.b-antiguo{background:rgba(42,90,26,.1);color:var(--verde);border:1px solid rgba(42,90,26,.2)}
.b-nuevo{background:rgba(26,42,90,.1);color:#1A2A5A;border:1px solid rgba(26,42,90,.2)}
.b-facil{background:rgba(42,90,26,.08);color:var(--verde)}
.b-medio{background:rgba(200,120,32,.1);color:var(--ambar)}
.b-dificil{background:rgba(122,26,16,.1);color:var(--rojo)}
.b-activo{background:rgba(42,90,26,.1);color:var(--verde)}
.b-inactivo{background:rgba(122,26,16,.08);color:var(--rojo)}

/* Botones inline */
.btn-sm{display:inline-block;padding:4px 10px;border-radius:5px;font-size:.75rem;font-weight:600;cursor:pointer;border:none;transition:all .2s;font-family:'DM Sans',sans-serif}
.btn-editar{background:rgba(200,120,32,.1);color:var(--ambar)}
.btn-editar:hover{background:var(--ambar);color:#fff}
.btn-toggle{background:rgba(42,90,26,.1);color:var(--verde)}
.btn-toggle:hover{background:var(--verde);color:#fff}
.btn-eliminar{background:rgba(122,26,16,.08);color:var(--rojo)}
.btn-eliminar:hover{background:var(--rojo);color:#fff}

/* Formulario */
.form-card{background:var(--blanco);border-radius:var(--radio);border:1px solid rgba(92,54,20,.1);padding:1.5rem;box-shadow:0 2px 12px rgba(92,54,20,.08)}
.form-titulo{font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:700;margin-bottom:1.2rem;padding-bottom:.6rem;border-bottom:1px solid rgba(92,54,20,.08)}
.form-grupo{margin-bottom:1rem}
.form-grupo label{display:block;font-size:.78rem;font-weight:600;color:var(--gris);margin-bottom:.3rem;text-transform:uppercase;letter-spacing:.04em}
.form-grupo input,.form-grupo select,.form-grupo textarea{
  width:100%;border:1.5px solid rgba(92,54,20,.2);border-radius:7px;
  padding:.55rem .9rem;font-family:'DM Sans',sans-serif;font-size:.9rem;
  color:var(--cafe);background:var(--bg);outline:none;transition:border-color .2s
}
.form-grupo input:focus,.form-grupo select:focus,.form-grupo textarea:focus{border-color:var(--ambar)}
.form-grupo textarea{resize:vertical;min-height:60px}

.pistas-lista-form{display:flex;flex-direction:column;gap:.5rem;margin-bottom:.5rem}
.pista-input-row{display:flex;gap:.4rem;align-items:center}
.pista-input-row span{font-size:.72rem;color:var(--gris);min-width:1.8rem;text-align:right;font-weight:600}
.pista-input-row input{flex:1}

.btn-agregar-pista{font-size:.78rem;color:var(--ambar);background:none;border:1px dashed var(--ambar);
  border-radius:6px;padding:4px 12px;cursor:pointer;transition:all .2s;width:100%}
.btn-agregar-pista:hover{background:rgba(200,120,32,.1)}

.btn-guardar{width:100%;padding:.85rem;background:linear-gradient(135deg,var(--cafe2),var(--ambar));
  border:none;border-radius:var(--radio);color:#fff;font-family:'DM Sans',sans-serif;
  font-size:.95rem;font-weight:600;cursor:pointer;transition:all .2s;margin-top:.5rem}
.btn-guardar:hover{transform:translateY(-1px);box-shadow:0 4px 16px rgba(92,54,20,.3)}

/* Alerta */
.alerta{padding:.8rem 1rem;border-radius:var(--radio);margin-bottom:1rem;font-size:.9rem;font-weight:500}
.alerta-ok{background:rgba(42,90,26,.1);border:1px solid rgba(42,90,26,.25);color:var(--verde)}
.alerta-error{background:rgba(122,26,16,.1);border:1px solid rgba(122,26,16,.25);color:var(--rojo)}

/* Acciones */
.acciones-form{display:flex;gap:.5rem;justify-content:flex-end;margin-top:1rem}
</style>
</head>
<body>

<nav>
  <h1>Admin — ¿Quién Soy?</h1>
  <a href="index.php" class="activa">Personajes</a>
  <a href="historial.php">Historial</a>
  <a href="../index.php">← Volver al juego</a>
</nav>

<div class="contenedor">
  <div>
    <!-- Stats -->
    <div class="stats">
      <div class="stat-box"><div class="stat-num"><?= $stats['total'] ?></div><div class="stat-lab">Total personajes</div></div>
      <div class="stat-box"><div class="stat-num"><?= $stats['activos'] ?></div><div class="stat-lab">Activos</div></div>
      <div class="stat-box"><div class="stat-num"><?= $stats['antiguo'] ?></div><div class="stat-lab">A. Testamento</div></div>
      <div class="stat-box"><div class="stat-num"><?= $total_partidas ?></div><div class="stat-lab">Partidas jugadas</div></div>
    </div>

    <?php if ($msg): ?>
    <div class="alerta alerta-<?= $msg['tipo'] ?>"><?= htmlspecialchars($msg['texto']) ?></div>
    <?php endif; ?>

    <!-- Tabla de personajes -->
    <div class="card">
      <div class="card-titulo">Personajes bíblicos</div>
      <table>
        <thead><tr>
          <th>Nombre</th><th>Testamento</th><th>Dificultad</th><th>Pistas</th><th>Estado</th><th>Acciones</th>
        </tr></thead>
        <tbody>
        <?php foreach ($personajes as $p): ?>
        <tr>
          <td><strong><?= htmlspecialchars($p['nombre']) ?></strong></td>
          <td><span class="badge b-<?= $p['testamento'] ?>"><?= ucfirst($p['testamento']) ?></span></td>
          <td><span class="badge b-<?= $p['dificultad'] ?>"><?= ucfirst($p['dificultad']) ?></span></td>
          <td><?= $p['num_pistas'] ?></td>
          <td><span class="badge <?= $p['activo'] ? 'b-activo' : 'b-inactivo' ?>"><?= $p['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
          <td>
            <a href="?editar=<?= $p['id'] ?>" class="btn-sm btn-editar">Editar</a>
            <form method="post" style="display:inline">
              <input type="hidden" name="accion" value="toggle_activo">
              <input type="hidden" name="id" value="<?= $p['id'] ?>">
              <button type="submit" class="btn-sm btn-toggle"><?= $p['activo'] ? 'Desactivar' : 'Activar' ?></button>
            </form>
            <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar este personaje?')">
              <input type="hidden" name="accion" value="eliminar">
              <input type="hidden" name="id" value="<?= $p['id'] ?>">
              <button type="submit" class="btn-sm btn-eliminar">Eliminar</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Formulario -->
  <div class="form-card">
    <div class="form-titulo">
      <?= $editar ? '✏️ Editar: ' . htmlspecialchars($editar['nombre']) : '+ Nuevo personaje' ?>
    </div>

    <form method="post" id="form-personaje">
      <input type="hidden" name="accion" value="guardar_personaje">
      <?php if ($editar): ?><input type="hidden" name="id" value="<?= $editar['id'] ?>"><?php endif; ?>

      <div class="form-grupo">
        <label>Nombre del personaje</label>
        <input type="text" name="nombre" required placeholder="Ej: Moisés"
               value="<?= htmlspecialchars($editar['nombre'] ?? '') ?>">
      </div>

      <div class="form-grupo">
        <label>Testamento</label>
        <select name="testamento">
          <option value="antiguo" <?= ($editar['testamento'] ?? '') === 'antiguo' ? 'selected' : '' ?>>Antiguo Testamento</option>
          <option value="nuevo"   <?= ($editar['testamento'] ?? '') === 'nuevo'   ? 'selected' : '' ?>>Nuevo Testamento</option>
        </select>
      </div>

      <div class="form-grupo">
        <label>Dificultad</label>
        <select name="dificultad">
          <option value="facil"   <?= ($editar['dificultad'] ?? '') === 'facil'   ? 'selected' : '' ?>>Fácil</option>
          <option value="medio"   <?= ($editar['dificultad'] ?? 'medio') === 'medio'  ? 'selected' : '' ?>>Media</option>
          <option value="dificil" <?= ($editar['dificultad'] ?? '') === 'dificil' ? 'selected' : '' ?>>Difícil</option>
        </select>
      </div>

      <div class="form-grupo">
        <label>Pistas (de menos a más obvia)</label>
        <div class="pistas-lista-form" id="pistas-form">
          <?php
          $pistas_val = $editar['pistas'] ?? ['','','','',''];
          foreach ($pistas_val as $i => $pt):
          ?>
          <div class="pista-input-row">
            <span>#<?= $i + 1 ?></span>
            <input type="text" name="pistas[]"
                   placeholder="Pista <?= $i+1 ?> (la <?= $i===0?'más difícil':'más fácil' ?>)"
                   value="<?= htmlspecialchars($pt) ?>">
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn-agregar-pista" id="btn-add-pista">+ Agregar pista</button>
      </div>

      <button type="submit" class="btn-guardar">Guardar personaje</button>
      <?php if ($editar): ?>
      <div class="acciones-form">
        <a href="index.php" style="font-size:.82rem;color:var(--gris)">Cancelar edición</a>
      </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<script>
let pistaCount = document.querySelectorAll('.pista-input-row').length;
document.getElementById('btn-add-pista').addEventListener('click', () => {
  pistaCount++;
  const row = document.createElement('div');
  row.className = 'pista-input-row';
  row.innerHTML = `<span>#${pistaCount}</span>
    <input type="text" name="pistas[]" placeholder="Pista ${pistaCount}">`;
  document.getElementById('pistas-form').appendChild(row);
});
</script>
</body>
</html>
