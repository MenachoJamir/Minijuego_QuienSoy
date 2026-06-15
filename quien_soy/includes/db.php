<?php
// ══════════════════════════════════════════════
//  CONFIGURACIÓN DE BASE DE DATOS
//  Cambia estos valores según tu servidor
// ══════════════════════════════════════════════
define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // Tu usuario MySQL
define('DB_PASS', '');            // Tu contraseña MySQL
define('DB_NAME', 'quien_soy_db');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            // Primero conectar sin base de datos para crearla si no existe
            $dsn_init = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
            $init = new PDO($dsn_init, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $init->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            crearTablas($pdo);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Error de conexión: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

function crearTablas(PDO $pdo): void {
    // Tabla de personajes
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS personajes (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            nombre      VARCHAR(100) NOT NULL,
            testamento  ENUM('antiguo','nuevo') NOT NULL DEFAULT 'antiguo',
            dificultad  ENUM('facil','medio','dificil') NOT NULL DEFAULT 'medio',
            activo      TINYINT(1) NOT NULL DEFAULT 1,
            creado_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");

    // Tabla de pistas (cada personaje tiene hasta 5 pistas, ordenadas de mayor a menor dificultad)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pistas (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            personaje_id INT NOT NULL,
            orden        TINYINT NOT NULL,   -- 1=más difícil (menos puntos), 5=más fácil (menos puntos)
            texto        TEXT NOT NULL,
            FOREIGN KEY (personaje_id) REFERENCES personajes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");

    // Tabla de partidas (historial)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS partidas (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            jugador       VARCHAR(80) NOT NULL,
            personaje_id  INT NOT NULL,
            pistas_usadas TINYINT NOT NULL,
            puntos        INT NOT NULL,
            adivinado     TINYINT(1) NOT NULL DEFAULT 0,
            jugado_en     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (personaje_id) REFERENCES personajes(id)
        ) ENGINE=InnoDB
    ");

    // Tabla de sesión de juego grupal
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sesiones_juego (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            codigo       VARCHAR(8) NOT NULL UNIQUE,
            jugadores    JSON NOT NULL,
            estado       ENUM('esperando','jugando','terminada') DEFAULT 'esperando',
            ronda_actual INT DEFAULT 1,
            creado_en    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");

    // Insertar datos de ejemplo si no existen
    $count = $pdo->query("SELECT COUNT(*) FROM personajes")->fetchColumn();
    if ($count == 0) {
        insertarDatosEjemplo($pdo);
    }
}

function insertarDatosEjemplo(PDO $pdo): void {
    $personajes = [
        [
            'nombre' => 'Moisés', 'testamento' => 'antiguo', 'dificultad' => 'facil',
            'pistas' => [
                'Dios le habló desde una zarza ardiente que no se consumía.',
                'Pasé 40 años en el desierto guiando a mi pueblo.',
                'Recibí las tablas de la ley en el monte Sinaí.',
                'Dividí el Mar Rojo para que mi pueblo cruzara.',
                'Fui colocado en una cesta de juncos sobre el río Nilo cuando era bebé.',
            ]
        ],
        [
            'nombre' => 'David', 'testamento' => 'antiguo', 'dificultad' => 'facil',
            'pistas' => [
                'Escribí muchos de los Salmos que se cantan en la iglesia.',
                'Fui ungido rey mientras aún era el menor de mis hermanos.',
                'Tuve una relación con Betsabé que trajo consecuencias graves.',
                'De joven era pastor de ovejas en Belén.',
                'Maté al gigante filisteo con una piedra y una honda.',
            ]
        ],
        [
            'nombre' => 'María Magdalena', 'testamento' => 'nuevo', 'dificultad' => 'medio',
            'pistas' => [
                'Fui la primera persona en ver al Señor resucitado.',
                'Seguí a Jesús desde Galilea y lo acompañé hasta la cruz.',
                'Jesús me liberó de siete demonios.',
                'Mi nombre viene de la ciudad de Magdala, junto al Mar de Galilea.',
                'Fui al sepulcro muy temprano el primer día de la semana con especias aromáticas.',
            ]
        ],
        [
            'nombre' => 'Pablo', 'testamento' => 'nuevo', 'dificultad' => 'facil',
            'pistas' => [
                'Escribí más de la mitad de los libros del Nuevo Testamento.',
                'Hice tres grandes viajes misioneros por el mundo romano.',
                'Estuve en prisión varias veces por predicar el evangelio.',
                'Antes de convertirme perseguía y mataba a los cristianos.',
                'Tuve una experiencia sobrenatural en el camino a Damasco que cambió mi vida.',
            ]
        ],
        [
            'nombre' => 'Noé', 'testamento' => 'antiguo', 'dificultad' => 'facil',
            'pistas' => [
                'Dios hizo un pacto conmigo usando el arcoíris como señal.',
                'Después del diluvio planté una viña y hice vino.',
                'Viví 950 años según la Biblia.',
                'Entré al arca con mi familia: mi esposa, mis tres hijos y sus esposas.',
                'Construí una enorme embarcación para salvarme del diluvio universal.',
            ]
        ],
        [
            'nombre' => 'Ester', 'testamento' => 'antiguo', 'dificultad' => 'medio',
            'pistas' => [
                'Un libro completo de la Biblia lleva mi nombre.',
                'Mi primo Mardoqueo me crió como a su hija.',
                'Con valentía dije: "Si perezco, que perezca".',
                'Arriesgué mi vida al presentarme ante el rey sin ser llamada.',
                'Fui una reina judía en Persia que salvó a mi pueblo de un exterminio.',
            ]
        ],
        [
            'nombre' => 'Daniel', 'testamento' => 'antiguo', 'dificultad' => 'medio',
            'pistas' => [
                'Interpreté sueños y visiones que otros no podían comprender.',
                'Mis compañeros Sadrac, Mesac y Abed-nego fueron lanzados al horno de fuego.',
                'Rechacé comer los manjares del rey para no contaminarme.',
                'Fui llevado cautivo a Babilonia siendo joven.',
                'Sobreviví una noche entera en un foso lleno de leones.',
            ]
        ],
        [
            'nombre' => 'Pedro', 'testamento' => 'nuevo', 'dificultad' => 'facil',
            'pistas' => [
                'Jesús me dijo: "Sobre esta roca edificaré mi iglesia".',
                'Caminé sobre el agua, pero me hundí por falta de fe.',
                'Negué tres veces a Jesús antes de que cantara el gallo.',
                'Era pescador junto con mi hermano Andrés.',
                'Jesús me cambió el nombre de Simón a Cefas, que significa roca.',
            ]
        ],
        [
            'nombre' => 'Salomón', 'testamento' => 'antiguo', 'dificultad' => 'medio',
            'pistas' => [
                'Se me atribuyen los libros de Proverbios, Eclesiastés y Cantares.',
                'Tuve 700 esposas y 300 concubinas, lo que alejó mi corazón de Dios.',
                'La reina de Sabá vino de lejos para escuchar mi sabiduría.',
                'Construí el Templo de Dios en Jerusalén.',
                'Dios se me apareció en sueños y me ofreció lo que quisiera: pedí sabiduría.',
            ]
        ],
        [
            'nombre' => 'Rut', 'testamento' => 'antiguo', 'dificultad' => 'medio',
            'pistas' => [
                'Mi hijo Obed fue abuelo del rey David.',
                'Booz me redimió siguiendo la ley del pariente redentor.',
                'Recogía espigas en los campos después de los segadores.',
                'Dejé mi tierra Moab para acompañar a mi suegra Noemí.',
                'Dije: "Tu pueblo será mi pueblo y tu Dios será mi Dios".',
            ]
        ],
        [
            'nombre' => 'Juan el Bautista', 'testamento' => 'nuevo', 'dificultad' => 'medio',
            'pistas' => [
                'Herodes Antipas ordenó mi decapitación por pedido de Salomé.',
                'Vestía con pelo de camello y comía langostas y miel silvestre.',
                'Jesús dijo que entre los nacidos de mujer no había surgido nadie mayor que yo.',
                'Bautizaba a las multitudes en el río Jordán.',
                'Fui el precursor de Jesús, la voz que clamaba en el desierto.',
            ]
        ],
        [
            'nombre' => 'Abraham', 'testamento' => 'antiguo', 'dificultad' => 'facil',
            'pistas' => [
                'Dios me prometió que en mi descendencia serían benditas todas las naciones.',
                'A los 99 años recibí la señal del pacto: la circuncisión.',
                'Dios me pidió que sacrificara a mi hijo Isaac, pero lo detuvo.',
                'Dejé mi tierra Ur de los Caldeos obedeciendo la voz de Dios.',
                'Soy llamado "padre de la fe" y padre de muchas naciones.',
            ]
        ],
        [
            'nombre' => 'Zaqueo', 'testamento' => 'nuevo', 'dificultad' => 'dificil',
            'pistas' => [
                'Prometí devolver cuatro veces lo que hubiera robado a alguien.',
                'Jesús me invitó a cenar en mi propia casa.',
                'Era jefe de los publicanos en Jericó y muy rico.',
                'Era de baja estatura y no podía ver a Jesús entre la multitud.',
                'Subí a un árbol sicómoro para poder ver pasar a Jesús.',
            ]
        ],
        [
            'nombre' => 'Tomás', 'testamento' => 'nuevo', 'dificultad' => 'medio',
            'pistas' => [
                'Jesús me dijo: "No seas incrédulo, sino creyente".',
                'Cuando los demás dijeron haber visto a Jesús, no lo creí.',
                'Jesús me mostró las marcas de los clavos y la herida de su costado.',
                'Me llaman "el Mellizo" en arameo.',
                'Dije que no creería en la resurrección a menos que metiera mis dedos en las heridas.',
            ]
        ],
        [
            'nombre' => 'Elías', 'testamento' => 'antiguo', 'dificultad' => 'dificil',
            'pistas' => [
                'Un ángel me tocó dos veces y me dejó comida para el camino.',
                'Subí al cielo en un carro de fuego sin morir.',
                'En el monte Horeb escuché a Dios en un silbo apacible.',
                'Desafié a 450 profetas de Baal en el monte Carmelo.',
                'Anuncié que no lloverían ni rocío ni lluvia por tres años.',
            ]
        ],
    ];

    $stmtP = $pdo->prepare("INSERT INTO personajes (nombre, testamento, dificultad) VALUES (?, ?, ?)");
    $stmtI = $pdo->prepare("INSERT INTO pistas (personaje_id, orden, texto) VALUES (?, ?, ?)");

    foreach ($personajes as $p) {
        $stmtP->execute([$p['nombre'], $p['testamento'], $p['dificultad']]);
        $pid = $pdo->lastInsertId();
        foreach ($p['pistas'] as $i => $texto) {
            $stmtI->execute([$pid, $i + 1, $texto]);
        }
    }
}
