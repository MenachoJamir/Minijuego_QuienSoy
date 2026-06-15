<?php
session_start();
require_once __DIR__ . '/includes/db.php';

// ══════════════════════════════════════════════
//  LÓGICA DE JUEGO — AJAX
// ══════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $accion = $_POST['accion'] ?? '';
    $pdo    = getDB();

    // ── Iniciar partida grupal ─────────────────
    if ($accion === 'iniciar') {
        $nombres   = json_decode($_POST['nombres'] ?? '[]', true);
        $filtro    = $_POST['filtro'] ?? 'todos';
        $dificultad= $_POST['dificultad'] ?? 'todos';

        $nombres = array_values(array_filter(array_map('trim', $nombres)));
        if (count($nombres) < 1) { echo json_encode(['error' => 'Ingresa al menos un jugador']); exit; }

        // Obtener personajes según filtros
        $where = ['p.activo = 1'];
        $params = [];
        if ($filtro !== 'todos')     { $where[] = 'p.testamento = ?'; $params[] = $filtro; }
        if ($dificultad !== 'todos') { $where[] = 'p.dificultad = ?'; $params[] = $dificultad; }

        $sql = "SELECT p.id, p.nombre, p.testamento, p.dificultad,
                       GROUP_CONCAT(pi.texto ORDER BY pi.orden SEPARATOR '|||') AS pistas_concat
                FROM personajes p
                JOIN pistas pi ON pi.personaje_id = p.id
                WHERE " . implode(' AND ', $where) . "
                GROUP BY p.id
                ORDER BY RAND()
                LIMIT 10";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $personajes_raw = $stmt->fetchAll();

        if (count($personajes_raw) === 0) { echo json_encode(['error' => 'No hay personajes con ese filtro']); exit; }

        // Construir array limpio
        $personajes = [];
        foreach ($personajes_raw as $row) {
            $personajes[] = [
                'id'          => (int)$row['id'],
                'nombre'      => $row['nombre'],
                'testamento'  => $row['testamento'],
                'dificultad'  => $row['dificultad'],
                'pistas'      => explode('|||', $row['pistas_concat']),
            ];
        }

        // Construir jugadores
        $jugadores = [];
        foreach ($nombres as $n) {
            $jugadores[] = ['nombre' => $n, 'puntos' => 0, 'aciertos' => 0, 'errores_ronda' => 0];
        }

        $_SESSION['qs'] = [
            'jugadores'   => $jugadores,
            'personajes'  => $personajes,
            'ronda'       => 0,           // índice del personaje actual
            'turno'       => 0,           // índice del jugador activo
            'pista_idx'   => 0,           // cuántas pistas se han mostrado (0-based)
            'estado'      => 'jugando',   // jugando | fin_ronda | fin_juego
        ];

        echo json_encode(['ok' => true, 'estado' => buildEstado()]);
        exit;
    }

    // ── Siguiente pista ────────────────────────
    if ($accion === 'pista') {
        $s = &$_SESSION['qs'];
        $personaje = $s['personajes'][$s['ronda']];
        $max_pistas = count($personaje['pistas']);
        if ($s['pista_idx'] < $max_pistas - 1) {
            $s['pista_idx']++;
        }
        echo json_encode(['estado' => buildEstado()]);
        exit;
    }

    // ── Responder ──────────────────────────────
    if ($accion === 'responder') {
        $s        = &$_SESSION['qs'];
        $respuesta = strtolower(trim($_POST['respuesta'] ?? ''));
        $personaje = $s['personajes'][$s['ronda']];
        $correcto  = strtolower($personaje['nombre']);

        // Comparación flexible
        $acierto = ($respuesta === $correcto)
                || levenshtein($respuesta, $correcto) <= 2
                || similar_text($respuesta, $correcto) / max(strlen($correcto), 1) * 100 >= 75;

        $puntos_ganados = 0;
        if ($acierto) {
            // Puntos inversamente proporcionales a las pistas usadas
            // Pista 1 = 5 pts, Pista 2 = 4, Pista 3 = 3, Pista 4 = 2, Pista 5 = 1
            $puntos_ganados = max(1, 6 - ($s['pista_idx'] + 1));
            // Bonus por dificultad
            if ($personaje['dificultad'] === 'medio')    $puntos_ganados += 1;
            if ($personaje['dificultad'] === 'dificil')  $puntos_ganados += 2;

            $s['jugadores'][$s['turno']]['puntos']   += $puntos_ganados;
            $s['jugadores'][$s['turno']]['aciertos'] += 1;
        } else {
            $s['jugadores'][$s['turno']]['errores_ronda']++;
        }

        // Guardar en historial
        try {
            $pdo->prepare("INSERT INTO partidas (jugador, personaje_id, pistas_usadas, puntos, adivinado)
                           VALUES (?, ?, ?, ?, ?)")
                ->execute([
                    $s['jugadores'][$s['turno']]['nombre'],
                    $personaje['id'],
                    $s['pista_idx'] + 1,
                    $puntos_ganados,
                    $acierto ? 1 : 0
                ]);
        } catch (Exception $e) {}

        if ($acierto) {
            // Avanzar ronda
            $s['ronda']++;
            $s['turno'] = ($s['turno'] + 1) % count($s['jugadores']);
            $s['pista_idx'] = 0;
            if ($s['ronda'] >= count($s['personajes'])) {
                $s['estado'] = 'fin_juego';
            } else {
                $s['estado'] = 'fin_ronda';
            }
        }

        echo json_encode([
            'acierto'        => $acierto,
            'puntos_ganados' => $puntos_ganados,
            'respuesta_correcta' => $personaje['nombre'],
            'estado'         => buildEstado()
        ]);
        exit;
    }

    // ── Pasar personaje ────────────────────────
    if ($accion === 'pasar') {
        $s = &$_SESSION['qs'];
        $s['ronda']++;
        $s['turno'] = ($s['turno'] + 1) % count($s['jugadores']);
        $s['pista_idx'] = 0;
        if ($s['ronda'] >= count($s['personajes'])) {
            $s['estado'] = 'fin_juego';
        } else {
            $s['estado'] = 'fin_ronda';
        }
        echo json_encode(['estado' => buildEstado()]);
        exit;
    }

    // ── Continuar a siguiente ronda ────────────
    if ($accion === 'continuar') {
        $s = &$_SESSION['qs'];
        $s['estado'] = 'jugando';
        echo json_encode(['estado' => buildEstado()]);
        exit;
    }

    echo json_encode(['error' => 'Acción desconocida']);
    exit;
}

function buildEstado(): array {
    $s = $_SESSION['qs'];
    $ronda = $s['ronda'];
    $total = count($s['personajes']);

    if ($s['estado'] === 'fin_juego') {
        return [
            'estado'    => 'fin_juego',
            'jugadores' => $s['jugadores'],
        ];
    }

    $personaje = $s['personajes'][$ronda];
    // Solo devolver las pistas hasta la actual
    $pistas_visibles = array_slice($personaje['pistas'], 0, $s['pista_idx'] + 1);

    return [
        'estado'          => $s['estado'],
        'jugadores'       => $s['jugadores'],
        'turno'           => $s['turno'],
        'ronda_num'       => $ronda + 1,
        'total_rondas'    => $total,
        'pista_idx'       => $s['pista_idx'],
        'total_pistas'    => count($personaje['pistas']),
        'pistas_visibles' => $pistas_visibles,
        'testamento'      => $personaje['testamento'],
        'dificultad'      => $personaje['dificultad'],
        'nombre_correcto' => ($s['estado'] === 'fin_ronda') ? $personaje['nombre'] : null,
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>¿Quién Soy? — Personajes Bíblicos</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<!-- ═══════════════════════════════════════ -->
<!--  PANTALLA 1 — PORTADA + REGISTRO      -->
<!-- ═══════════════════════════════════════ -->
<div id="p-inicio" class="pantalla activa">
  <div class="inicio-bg">
    <div class="particula" style="--x:10%;--y:20%;--s:3px;--d:4s"></div>
    <div class="particula" style="--x:80%;--y:10%;--s:5px;--d:6s"></div>
    <div class="particula" style="--x:60%;--y:70%;--s:4px;--d:5s"></div>
    <div class="particula" style="--x:30%;--y:80%;--s:2px;--d:7s"></div>
    <div class="particula" style="--x:90%;--y:50%;--s:6px;--d:3s"></div>
  </div>

  <div class="inicio-contenido">
    <div class="portada">
      <div class="portada-signo">✦</div>
      <h1>¿Quién Soy?</h1>
      <p class="portada-sub">Descubre el personaje bíblico a través de sus pistas</p>
    </div>

    <div class="setup-card">
      <!-- Jugadores -->
      <div class="setup-seccion">
        <h3>Jugadores</h3>
        <div class="jugadores-cantidad">
          <button id="btn-menos" class="btn-qty">−</button>
          <span id="num-jug">3</span>
          <button id="btn-mas"  class="btn-qty">+</button>
        </div>
        <div id="inputs-nombres"></div>
      </div>

      <!-- Filtros -->
      <div class="setup-seccion">
        <h3>Filtros de juego</h3>
        <div class="filtros-grid">
          <div class="filtro-grupo">
            <label>Testamento</label>
            <select id="sel-testamento">
              <option value="todos">Ambos</option>
              <option value="antiguo">Antiguo</option>
              <option value="nuevo">Nuevo</option>
            </select>
          </div>
          <div class="filtro-grupo">
            <label>Dificultad</label>
            <select id="sel-dificultad">
              <option value="todos">Todas</option>
              <option value="facil">Fácil</option>
              <option value="medio">Media</option>
              <option value="dificil">Difícil</option>
            </select>
          </div>
        </div>
      </div>

      <button id="btn-comenzar" class="btn-primario">
        Comenzar juego
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
      </button>

      <a href="admin/" class="link-admin">⚙ Panel de administración</a>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════ -->
<!--  PANTALLA 2 — JUEGO ACTIVO            -->
<!-- ═══════════════════════════════════════ -->
<div id="p-juego" class="pantalla">

  <!-- Barra superior -->
  <div class="topbar">
    <div class="topbar-ronda">
      Ronda <span id="t-ronda">1</span> / <span id="t-total">10</span>
    </div>
    <div id="topbar-jugadores" class="topbar-jugadores"></div>
    <div class="topbar-turno">
      Turno de <strong id="t-nombre-turno">—</strong>
    </div>
  </div>

  <!-- Área central -->
  <main class="juego-main">

    <!-- Tarjeta misterio -->
    <div class="misterio-card" id="misterio-card">
      <div class="misterio-header">
        <div class="misterio-icono" id="m-icono">?</div>
        <div class="misterio-meta">
          <span class="badge-testamento" id="m-testamento">—</span>
          <span class="badge-dificultad" id="m-dificultad">—</span>
        </div>
      </div>

      <div class="pistas-lista" id="pistas-lista">
        <!-- Se generan dinámicamente -->
      </div>

      <div class="pistas-contador">
        <span id="p-usadas">1</span> de <span id="p-total">5</span> pistas reveladas
        <div class="pistas-dots" id="pistas-dots"></div>
      </div>
    </div>

    <!-- Panel de respuesta -->
    <div class="respuesta-panel">
      <div class="puntos-preview" id="puntos-preview">
        <span class="pts-num" id="pts-num">5</span>
        <span class="pts-label">puntos si aciertas ahora</span>
      </div>

      <div class="respuesta-input-wrap">
        <input type="text" id="inp-respuesta"
               placeholder="¿Quién es este personaje?"
               autocomplete="off" autocorrect="off" spellcheck="false">
        <button id="btn-responder" class="btn-responder">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
        </button>
      </div>

      <div class="acciones-juego">
        <button id="btn-pista" class="btn-pista">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z"/></svg>
          Siguiente pista (−1 punto)
        </button>
        <button id="btn-pasar" class="btn-pasar">
          Revelar y pasar →
        </button>
      </div>
    </div>
  </main>
</div>

<!-- ═══════════════════════════════════════ -->
<!--  PANTALLA 3 — RESULTADO DE RONDA      -->
<!-- ═══════════════════════════════════════ -->
<div id="p-ronda" class="pantalla">
  <div class="ronda-card" id="ronda-card">
    <div class="ronda-resultado" id="ronda-resultado">
      <!-- acierto o fallo -->
    </div>
    <div class="ronda-personaje" id="ronda-personaje"></div>
    <div class="ronda-puntos-anim" id="ronda-puntos-anim"></div>
    <div class="ronda-ranking" id="ronda-ranking"></div>
    <button id="btn-continuar" class="btn-primario" style="margin-top:1.5rem">
      Siguiente personaje →
    </button>
  </div>
</div>

<!-- ═══════════════════════════════════════ -->
<!--  PANTALLA 4 — PODIO FINAL             -->
<!-- ═══════════════════════════════════════ -->
<div id="p-podio" class="pantalla">
  <div class="podio-contenedor">
    <h1 class="podio-titulo">¡Fin del juego!</h1>
    <p class="podio-sub">Resultados finales</p>
    <div class="podio-visual" id="podio-visual"></div>
    <div class="ranking-tabla" id="ranking-tabla"></div>
    <div class="podio-acciones">
      <button onclick="location.reload()" class="btn-primario">Nueva partida</button>
      <a href="admin/historial.php" class="btn-secundario">Ver historial</a>
    </div>
  </div>
</div>

<script src="assets/juego.js"></script>
</body>
</html>
