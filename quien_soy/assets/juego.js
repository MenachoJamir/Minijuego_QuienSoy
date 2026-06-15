// ══════════════════════════════════════════════
//  ¿QUIÉN SOY? — Lógica Frontend
// ══════════════════════════════════════════════

const COLORES = ['#5C3614','#1A2A5A','#2A4A1A','#7A1A10','#3A1A5A','#1A4A4A','#4A3A10','#3A1A30'];
const EMOJIS_TESTAMENTO = { antiguo: '📜', nuevo: '✝️' };
const EMOJIS_DIFICULTAD = { facil: '⭐', medio: '⭐⭐', dificil: '⭐⭐⭐' };

let estado = null;
let numJugadores = 3;

// ── Utilidad ───────────────────────────────────
function mostrarPantalla(id) {
  document.querySelectorAll('.pantalla').forEach(p => {
    p.classList.remove('activa');
    p.style.display = 'none';
  });
  const el = document.getElementById(id);
  el.style.display = 'flex';
  requestAnimationFrame(() => el.classList.add('activa'));
}

async function api(datos) {
  const fd = new FormData();
  Object.entries(datos).forEach(([k, v]) => fd.append(k, v));
  const r = await fetch('', { method: 'POST', body: fd });
  return r.json();
}

// ══════════════════════════════════════════════
//  PANTALLA 1 — REGISTRO
// ══════════════════════════════════════════════
function renderNombres() {
  const cont = document.getElementById('inputs-nombres');
  cont.innerHTML = '';
  for (let i = 0; i < numJugadores; i++) {
    const wrap = document.createElement('div');
    wrap.className = 'nombre-wrap';
    const badge = document.createElement('div');
    badge.className = `num-badge col-${i}`;
    badge.textContent = i + 1;
    const inp = document.createElement('input');
    inp.type = 'text';
    inp.placeholder = `Jugador ${i + 1}`;
    inp.maxLength = 20;
    inp.addEventListener('keydown', e => { if (e.key === 'Enter') document.getElementById('btn-comenzar').click(); });
    wrap.appendChild(badge);
    wrap.appendChild(inp);
    cont.appendChild(wrap);
  }
}

document.getElementById('btn-menos').addEventListener('click', () => {
  if (numJugadores > 1) { numJugadores--; document.getElementById('num-jug').textContent = numJugadores; renderNombres(); }
});
document.getElementById('btn-mas').addEventListener('click', () => {
  if (numJugadores < 8) { numJugadores++; document.getElementById('num-jug').textContent = numJugadores; renderNombres(); }
});

document.getElementById('btn-comenzar').addEventListener('click', async () => {
  const inputs = document.querySelectorAll('.nombre-wrap input');
  const nombres = [];
  inputs.forEach((inp, i) => nombres.push(inp.value.trim() || `Jugador ${i + 1}`));

  const data = await api({
    accion: 'iniciar',
    nombres: JSON.stringify(nombres),
    filtro: document.getElementById('sel-testamento').value,
    dificultad: document.getElementById('sel-dificultad').value,
  });

  if (data.error) { alert(data.error); return; }
  estado = data.estado;
  mostrarPantalla('p-juego');
  renderJuego();
});

renderNombres();

// ══════════════════════════════════════════════
//  PANTALLA 2 — JUEGO
// ══════════════════════════════════════════════
function renderJuego() {
  if (!estado) return;

  // Topbar
  document.getElementById('t-ronda').textContent = estado.ronda_num;
  document.getElementById('t-total').textContent = estado.total_rondas;
  document.getElementById('t-nombre-turno').textContent = estado.jugadores[estado.turno].nombre;

  const hudEl = document.getElementById('topbar-jugadores');
  hudEl.innerHTML = '';
  estado.jugadores.forEach((j, i) => {
    const chip = document.createElement('div');
    chip.className = 'hud-chip' + (i === estado.turno ? ' activo' : '');
    chip.innerHTML = `<span class="hud-chip-nom" style="color:${lighten(COLORES[i])}">${j.nombre}</span>
                      <span class="hud-chip-pts">${j.puntos}pts</span>`;
    hudEl.appendChild(chip);
  });

  // Icono central
  const icono = document.getElementById('m-icono');
  icono.textContent = EMOJIS_TESTAMENTO[estado.testamento] || '?';

  // Badges
  const bt = document.getElementById('m-testamento');
  bt.textContent = estado.testamento === 'antiguo' ? '📜 Antiguo' : '✝️ Nuevo';
  bt.className = 'badge-testamento' + (estado.testamento === 'nuevo' ? ' nuevo' : '');

  const bd = document.getElementById('m-dificultad');
  bd.textContent = (EMOJIS_DIFICULTAD[estado.dificultad] || '') + ' ' + (estado.dificultad || '');
  bd.className = 'badge-dificultad ' + (estado.dificultad || '');

  // Pistas
  const listaEl = document.getElementById('pistas-lista');
  listaEl.innerHTML = '';
  estado.pistas_visibles.forEach((texto, i) => {
    const item = document.createElement('div');
    item.className = 'pista-item';
    item.innerHTML = `<span class="pista-num">#${i + 1}</span>
                      <span class="pista-texto">${texto}</span>`;
    listaEl.appendChild(item);
  });

  // Dots
  const dotsEl = document.getElementById('pistas-dots');
  dotsEl.innerHTML = '';
  for (let i = 0; i < estado.total_pistas; i++) {
    const d = document.createElement('div');
    d.className = 'dot' + (i < estado.pista_idx ? ' pasado' : i === estado.pista_idx ? ' activo' : '');
    dotsEl.appendChild(d);
  }
  document.getElementById('p-usadas').textContent = estado.pista_idx + 1;
  document.getElementById('p-total').textContent  = estado.total_pistas;

  // Puntos potenciales
  const pts = calcularPuntos(estado);
  document.getElementById('pts-num').textContent = pts;

  // Botón pista
  const btnPista = document.getElementById('btn-pista');
  btnPista.disabled = (estado.pista_idx >= estado.total_pistas - 1);

  // Limpiar input
  const inp = document.getElementById('inp-respuesta');
  inp.value = '';
  inp.focus();
}

function calcularPuntos(est) {
  let base = Math.max(1, 6 - (est.pista_idx + 1));
  if (est.dificultad === 'medio')   base += 1;
  if (est.dificultad === 'dificil') base += 2;
  return base;
}

function lighten(hex) {
  // Devuelve versión más clara del color para texto
  const map = {
    '#5C3614': '#C87820', '#1A2A5A': '#6080D0',
    '#2A4A1A': '#60A040', '#7A1A10': '#D04040',
    '#3A1A5A': '#9060D0', '#1A4A4A': '#40A0A0',
    '#4A3A10': '#A08030', '#3A1A30': '#A060A0',
  };
  return map[hex] || '#E8A830';
}

// Siguiente pista
document.getElementById('btn-pista').addEventListener('click', async () => {
  const data = await api({ accion: 'pista' });
  estado = data.estado;
  renderJuego();
});

// Responder
document.getElementById('btn-responder').addEventListener('click', responder);
document.getElementById('inp-respuesta').addEventListener('keydown', e => {
  if (e.key === 'Enter') responder();
});

async function responder() {
  const inp = document.getElementById('inp-respuesta');
  const val = inp.value.trim();
  if (!val) { inp.focus(); return; }

  const data = await api({ accion: 'responder', respuesta: val });
  estado = data.estado;

  if (data.acierto !== undefined) {
    if (estado.estado === 'fin_juego') {
      mostrarRondaFin(data, true);
    } else {
      mostrarRondaFin(data, false);
    }
  }
}

// Pasar
document.getElementById('btn-pasar').addEventListener('click', async () => {
  if (!confirm('¿Pasar este personaje? Se revelará la respuesta correcta.')) return;
  const data = await api({ accion: 'pasar' });
  estado = data.estado;
  mostrarRondaFin({ acierto: false, puntos_ganados: 0, respuesta_correcta: estado.nombre_correcto || '?' }, estado.estado === 'fin_juego');
});

// ══════════════════════════════════════════════
//  PANTALLA 3 — FIN DE RONDA
// ══════════════════════════════════════════════
function mostrarRondaFin(data, esFin) {
  mostrarPantalla('p-ronda');

  const resEl = document.getElementById('ronda-resultado');
  const perEl = document.getElementById('ronda-personaje');
  const ptsEl = document.getElementById('ronda-puntos-anim');

  if (data.acierto) {
    resEl.className = 'ronda-resultado acierto';
    resEl.textContent = '¡Correcto! 🎉';
    ptsEl.textContent = `+${data.puntos_ganados} puntos`;
  } else {
    resEl.className = 'ronda-resultado fallo';
    resEl.textContent = data.puntos_ganados === undefined ? 'Personaje pasado' : '✗ Respuesta incorrecta';
    ptsEl.textContent = '';
  }

  perEl.textContent = data.respuesta_correcta || estado.nombre_correcto || '';

  // Mini ranking
  const rankEl = document.getElementById('ronda-ranking');
  const jugadores = [...estado.jugadores].map((j, i) => ({ ...j, idx: i }));
  jugadores.sort((a, b) => b.puntos - a.puntos);
  rankEl.innerHTML = jugadores.map((j, pos) => `
    <div class="rank-fila ${pos === 0 ? 'lider' : ''}">
      <span class="rank-pos">${pos + 1}°</span>
      <span class="rank-nom" style="color:${lighten(COLORES[j.idx])}">${j.nombre}</span>
      <span class="rank-pts">${j.puntos} pts</span>
    </div>`).join('');

  const btnCont = document.getElementById('btn-continuar');
  if (esFin) {
    btnCont.textContent = '🏆 Ver resultados finales';
    btnCont.onclick = () => mostrarPodio();
  } else {
    btnCont.textContent = 'Siguiente personaje →';
    btnCont.onclick = async () => {
      const data = await api({ accion: 'continuar' });
      estado = data.estado;
      mostrarPantalla('p-juego');
      renderJuego();
    };
  }
}

// ══════════════════════════════════════════════
//  PANTALLA 4 — PODIO
// ══════════════════════════════════════════════
function mostrarPodio() {
  mostrarPantalla('p-podio');

  const jugadores = [...estado.jugadores].map((j, i) => ({ ...j, idx: i }));
  jugadores.sort((a, b) => b.puntos - a.puntos);

  // Podio visual top 3
  const podioEl = document.getElementById('podio-visual');
  podioEl.innerHTML = '';
  const orden = [1, 0, 2]; // 2°, 1°, 3°
  const medallas = ['🥇', '🥈', '🥉'];
  orden.forEach(pos => {
    const j = jugadores[pos];
    if (!j) return;
    const col = document.createElement('div');
    col.className = 'podio-col';
    col.style.order = pos === 0 ? 1 : pos === 1 ? 0 : 2;
    col.innerHTML = `
      <div class="podio-etiqueta" style="color:${lighten(COLORES[j.idx])}">${j.nombre}</div>
      <div class="podio-pts-vis">${j.puntos} pts</div>
      <div class="podio-barra">${medallas[pos] || ''}</div>`;
    podioEl.appendChild(col);
  });

  // Tabla ranking
  const tablaEl = document.getElementById('ranking-tabla');
  tablaEl.innerHTML = `
    <div class="rt-header">
      <div>#</div><div>Jugador</div><div style="text-align:right">Puntos</div><div style="text-align:right">Aciertos</div>
    </div>
    ${jugadores.map((j, i) => `
      <div class="rt-fila ${i === 0 ? 'top1' : ''}">
        <div class="rt-pos">${i + 1}°</div>
        <div class="rt-nom" style="color:${lighten(COLORES[j.idx])}">${j.nombre}</div>
        <div class="rt-pts">${j.puntos}</div>
        <div class="rt-ok">${j.aciertos} ✓</div>
      </div>`).join('')}`;

  lanzarConfeti();
}

function lanzarConfeti() {
  const cols = ['#E8A830','#FFD060','#C87820','#5C3614','#2A4A1A','#1A2A5A'];
  for (let i = 0; i < 70; i++) {
    setTimeout(() => {
      const el = document.createElement('div');
      el.className = 'confeti-bib';
      el.style.cssText = `left:${Math.random()*100}vw;top:-10px;background:${cols[Math.floor(Math.random()*cols.length)]};width:${6+Math.random()*8}px;height:${6+Math.random()*8}px;animation-duration:${2+Math.random()*2}s`;
      document.body.appendChild(el);
      setTimeout(() => el.remove(), 4200);
    }, i * 45);
  }
}
