# ❓ ¿Quién Soy? — Personajes Bíblicos

Un mini-juego web de adivinanzas inspirado en el clásico **"¿Quién soy?"**: se revelan pistas progresivas sobre un personaje bíblico y los jugadores deben adivinar quién es antes de que se acaben las pistas. Mientras menos pistas necesites, más puntos ganas.

## 📖 ¿De qué trata?

Cada ronda presenta un personaje bíblico oculto (Moisés, David, Pablo, Ester, etc.) y va mostrando hasta 5 pistas, ordenadas de la más difícil a la más obvia. El jugador en turno puede:

- **Responder** en cualquier momento con el nombre del personaje.
- **Pedir otra pista** (a costo de puntos).
- **Pasar** y revelar la respuesta para seguir con el siguiente personaje.

**Mecánicas principales:**
- 👥 Soporta **partidas grupales** con cualquier cantidad de jugadores, jugando por turnos.
- 🎯 **Filtros de juego**: por testamento (Antiguo / Nuevo) y por dificultad (Fácil / Media / Difícil).
- 🧩 **Puntaje dinámico**: adivinar con la primera pista da más puntos (5) que adivinar con la última (1), más un bono según la dificultad del personaje.
- ✅ Comparación de respuestas flexible (tolera errores de tipeo con distancia de Levenshtein y similitud de texto).
- 🏆 **Podio final** con el ranking de jugadores al terminar la partida.
- 🗄️ **Historial persistente**: cada partida jugada se guarda en base de datos para estadísticas.
- ⚙️ **Panel de administración** para gestionar el banco de personajes y pistas sin tocar código (crear, editar, activar/desactivar y eliminar personajes).

## 🎯 ¿A quién está dirigido?

Este proyecto está pensado para:

- **Grupos de estudio bíblico, iglesias o jóvenes cristianos** que quieran reforzar el conocimiento de personajes de la Biblia de forma entretenida y competitiva.
- **Familias o amigos** que buscan un juego de preguntas temático para reuniones o noches de juegos.
- **Desarrolladores en formación** que busquen un ejemplo práctico de una app PHP + MySQL + JS sin frameworks, con separación de lógica de backend, vistas y assets.

## 🛠️ Tecnologías usadas

- **PHP + PDO** — lógica del juego, manejo de sesión (`$_SESSION`) y acceso seguro a la base de datos (consultas preparadas).
- **MySQL** — almacenamiento de personajes, pistas, historial de partidas y sesiones de juego.
- **JavaScript** — interactividad de la interfaz y comunicación con el backend vía `fetch`/AJAX.
- **HTML5 + CSS3** — estructura y diseño visual (tipografías Playfair Display y DM Sans vía Google Fonts).

## 📂 Estructura del proyecto

```
quien_soy/
├── index.php           # Pantallas del juego (inicio, juego, ronda, podio) + lógica AJAX
├── includes/
│   └── db.php          # Conexión PDO + creación automática de tablas y datos de ejemplo
├── admin/
│   ├── index.php        # Panel CRUD para gestionar personajes y pistas
│   └── historial.php    # Historial de partidas y ranking de jugadores
└── assets/
    ├── juego.js
    └── style.css
```

## 🚀 Cómo ejecutarlo localmente

1. Necesitas un servidor con **PHP y MySQL** (recomendado: [Laragon](https://laragon.org/) o XAMPP).
2. Clona este repositorio:
   ```bash
   git clone https://github.com/MenachoJamir/Minijuego_QuienSoy.git
   ```
3. Abre `includes/db.php` y ajusta `DB_USER` / `DB_PASS` según tu configuración de MySQL (por defecto usa `root` sin contraseña).
4. Inicia tu servidor (Laragon, XAMPP, o el servidor embebido de PHP):
   ```bash
   php -S localhost:8000
   ```
5. Abre tu navegador en `http://localhost:8000/quien_soy/`. La base de datos `quien_soy_db` y sus tablas se crean automáticamente la primera vez, con 16 personajes bíblicos de ejemplo ya cargados.

## 🎮 Cómo se juega

1. En la pantalla inicial, ingresa los nombres de los jugadores y elige los filtros (testamento y dificultad).
2. Por turnos, cada jugador ve las pistas del personaje oculto y puede responder, pedir otra pista o pasar.
3. Al adivinar correctamente se suman puntos según la cantidad de pistas usadas y la dificultad del personaje.
4. Al terminar todas las rondas se muestra el **podio final** con los puntajes.
5. Desde el enlace **"Panel de administración"** se pueden agregar nuevos personajes y pistas, y desde **"Ver historial"** se puede consultar el ranking acumulado de todas las partidas jugadas.

## ✍️ Autor

Proyecto desarrollado por **Jamir Menacho** como parte de su portafolio de proyectos en desarrollo de software (PHP, MySQL, JavaScript).
