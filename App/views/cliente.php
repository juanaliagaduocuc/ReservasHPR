<?php
session_start();

if (!isset($_SESSION['cliente'], $_SESSION['idCliente'])) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$habitaciones = $conn->query(
    "SELECT h.*, c.nombre AS categoria 
     FROM habitacion h
     LEFT JOIN categoria c ON c.idCategoria = h.idCategoria
     WHERE h.estado = 'Disponible'
     ORDER BY h.valorDiario ASC"
);

$imagenes = [
    'Premium' => 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1000&q=80',
    'Turista' => 'https://images.unsplash.com/photo-1494526585095-c41746248156?auto=format&fit=crop&w=1000&q=80',
    'Económica' => 'https://images.unsplash.com/photo-1484154218962-a197022b5858?auto=format&fit=crop&w=1000&q=80',
];

function getRoomType($categoriaNombre)
{
    $categoria = strtolower(trim((string) $categoriaNombre));
    if ($categoria === 'premium') {
        return 'premium';
    }
    return 'economica';
}

function formatPrice($valor)
{
    return '$ ' . number_format((float) $valor, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Pacific Reef</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-sand: #e5e0d9;
            --text: #123c3a;
            --text-warm: #f1efe7;
            --muted: rgba(18, 60, 58, 0.7);
            --green: #0f6a6d;
        }

        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; background: var(--bg-sand); color: var(--text); font-family: 'Inter', sans-serif; }
        .page-shell { max-width: 1600px; margin: 0 auto; background: var(--bg-sand); }
        .topbar {
            background: rgba(246, 244, 240, 0.96);
            border: 2px solid rgba(13, 123, 154, 0.7);
            border-left: none; border-right: none;
            display: flex; align-items: center; justify-content: space-between;
            padding: 1.05rem 2.2rem 0.95rem; position: sticky; top: 0; z-index: 20;
        }
        .brand { display: inline-flex; align-items: center; gap: 0.8rem; font-weight: 600; letter-spacing: 0.06em; font-size: 1.05rem; text-transform: uppercase; color: var(--text); text-decoration: none; font-family: 'Cormorant Garamond', serif; }
        .brand-mark { width: 1.1rem; height: 1.1rem; border: 2px solid rgba(14, 81, 93, 0.9); border-radius: 50%; display: inline-block; position: relative; }
        .brand-mark::after { content: ""; position: absolute; inset: 0.2rem; border-radius: 50%; border: 1px solid rgba(14,81,93,0.9); }
        .top-nav { display: flex; align-items: center; gap: 1.1rem; font-size: 0.84rem; text-transform: uppercase; }
        .top-nav a { color: var(--text); text-decoration: none; padding: 0.5rem 0.9rem; border: 1px solid rgba(14, 81, 93, 0.5); border-radius: 999px; background: transparent; }
        .top-nav .cta { background: rgba(11, 65, 67, 0.06); border-color: rgba(13, 123, 154, 0.85); font-weight: 700; letter-spacing: 0.08em; padding-inline: 1.2rem; }
        .hero { position: relative; min-height: 610px; background: linear-gradient(90deg, rgba(4,21,22,0.76) 0%, rgba(8,31,34,0.56) 35%, rgba(8,31,34,0.36) 100%), url('https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1600&q=80') center/cover no-repeat; color: var(--text-warm); padding: 4.2rem 3.8rem 2.6rem; }
        .hero-inner { max-width: 1220px; margin: 0 auto; }
        .hero-tag { display: inline-block; font-size: 0.73rem; letter-spacing: 0.18em; text-transform: uppercase; color: rgba(247, 242, 234, 0.9); margin-bottom: 1.2rem; font-weight: 600; }
        .hero h1 { margin: 0; max-width: 1100px; font-family: 'Cormorant Garamond', serif; font-size: clamp(3.1rem, 5vw, 6rem); line-height: 0.9; letter-spacing: -0.05em; font-weight: 500; color: #f5efe7; }
        .hero-copy { max-width: 800px; margin-top: 1.5rem; font-size: 1.05rem; line-height: 1.5; color: rgba(246,241,235,0.9); }
        .chip-row { display: flex; flex-wrap: wrap; gap: 0.7rem; margin-top: 2rem; }
        .chip { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.52rem 0.9rem; border-radius: 999px; background: rgba(255,255,255,0.09); border: 1px solid rgba(255,255,255,0.28); font-size: 0.78rem; color: rgba(245,239,231,0.94); }
        .check { width: 0.82rem; height: 0.82rem; border-radius: 50%; border: 1px solid rgba(255,255,255,0.84); display: inline-block; position: relative; }
        .check::after { content: ""; position: absolute; inset: 0.16rem; border-radius: 50%; background: rgba(255,255,255,0.9); }
        .content-wrap { padding: 2.6rem 2.2rem 0; background: var(--bg-sand); }
        .selector { max-width: 1220px; margin: 0 auto; background: rgba(255,255,255,0.2); border: 1px solid rgba(16,78,90,0.25); border-radius: 999px; padding: 0.45rem; display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; box-shadow: 0 6px 18px rgba(18, 60, 58, 0.08); }
        .selector-option { appearance: none; border: none; background: transparent; padding: 1rem 1.2rem; border-radius: 999px; color: var(--text); font-size: 1rem; font-weight: 500; display: flex; align-items: center; justify-content: center; gap: 0.72rem; cursor: pointer; }
        .selector-option .icon { width: 1.45rem; height: 1.45rem; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; border: 1px solid rgba(18,60,58,0.4); font-size: 0.8rem; background: rgba(255,255,255,0.22); }
        .selector-option.active { background: linear-gradient(180deg, rgba(7,72,78,1), rgba(13,95,87,1)); color: #f6f3ee; }
        .listing-head { max-width: 1220px; margin: 2.6rem auto 1.3rem; display: flex; align-items: end; justify-content: space-between; gap: 1rem; }
        .listing-title { margin: 0; font-family: 'Cormorant Garamond', serif; font-size: clamp(2.6rem, 3.2vw, 4rem); line-height: 0.96; letter-spacing: -0.04em; font-weight: 500; color: var(--text); }
        .listing-sub { margin-top: 0.5rem; color: var(--muted); font-size: 0.92rem; }
        .sort { border: 1px solid rgba(18,60,58,0.4); border-radius: 999px; padding: 0.7rem 1rem; background: rgba(255,255,255,0.15); color: var(--muted); font-size: 0.8rem; white-space: nowrap; }
        .type-toolbar { max-width: 1220px; margin: 0 auto 1.2rem; display: flex; align-items: center; gap: 0.8rem; flex-wrap: wrap; color: var(--muted); }
        .pill { border: 1px solid rgba(18,60,58,0.35); border-radius: 999px; padding: 0.45rem 0.8rem; font-size: 0.73rem; background: rgba(255,255,255,0.08); color: var(--text); }
        .room-grid { max-width: 1220px; margin: 0 auto; display: grid; grid-template-columns: repeat(3, minmax(220px, 1fr)); gap: 1.3rem; padding-bottom: 3.5rem; }
        .room-card { background: rgba(255,255,255,0.14); border: 1px solid rgba(18,60,58,0.28); border-radius: 1.1rem; overflow: hidden; box-shadow: 0 8px 22px rgba(15, 58, 60, 0.06); }
        .room-image { height: 235px; background-size: cover; background-position: center; }
        .room-card_body { padding: 1rem 1rem 1.1rem; }
        .room-meta { display: flex; align-items: center; justify-content: space-between; gap: 0.8rem; margin-bottom: 0.8rem; }
        .room-price { font-size: 1.12rem; font-weight: 700; color: var(--text); }
        .rating { font-size: 0.82rem; color: var(--text); display: inline-flex; align-items: center; gap: 0.28rem; font-weight: 600; }
        .room-num { font-size: clamp(2rem, 4vw, 2.8rem); font-family: 'Cormorant Garamond', serif; letter-spacing: -0.04em; line-height: 1; color: var(--text); margin: 0; font-weight: 500; }
        .room-little { display: flex; align-items: center; justify-content: space-between; gap: 1rem; border-top: 1px solid rgba(18,60,58,0.22); padding-top: 0.9rem; margin-top: 0.8rem; }
        .price-tag { display: flex; flex-direction: column; gap: 0.18rem; color: var(--text); }
        .price-tag strong { font-size: 1.1rem; font-weight: 700; }
        .price-tag span { font-size: 0.7rem; color: var(--muted); text-transform: lowercase; }
        .reserve-btn { border: 1px solid rgba(11, 90, 97, 0.65); background: rgba(14, 81, 93, 0.04); color: var(--green); border-radius: 999px; padding: 0.7rem 1.1rem; font-size: 0.76rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; cursor: pointer; }
        .journey { max-width: 1220px; margin: 0 auto; display: grid; grid-template-columns: 1.1fr 1fr; gap: 1.4rem; padding-bottom: 2rem; }
        .experience { background: linear-gradient(rgba(12, 49, 52, 0.48), rgba(12,49,52,0.48)), url('https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1200&q=80') center/cover no-repeat; color: #f5efe7; border-radius: 1.4rem; min-height: 500px; display: flex; flex-direction: column; justify-content: flex-end; padding: 2rem 2rem 1.4rem; border: 1px solid rgba(12, 50, 54, 0.3); }
        .experience .eyebrow { display: inline-block; width: fit-content; font-size: 0.7rem; letter-spacing: 0.15em; text-transform: uppercase; padding: 0.45rem 0.7rem; border-radius: 999px; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.25); }
        .experience h3 { font-family: 'Cormorant Garamond', serif; font-size: clamp(2.6rem, 3vw, 4rem); margin: 1.1rem 0 0.8rem; letter-spacing: -0.04em; line-height: 0.92; }
        .experience p { margin: 0; max-width: 520px; line-height: 1.55; color: rgba(245,239,231,0.88); }
        .experience .cta { margin-top: 1.6rem; width: fit-content; background: transparent; color: #f5efe7; border: 1px solid rgba(245,239,231,0.7); border-radius: 999px; padding: 0.8rem 1.2rem; letter-spacing: 0.08em; text-transform: uppercase; font-size: 0.76rem; font-weight: 700; }
        .journey-card { border-radius: 1.4rem; overflow: hidden; background: rgba(255,255,255,0.15); border: 1px solid rgba(18,60,58,0.2); }
        .journey-card .image { height: 260px; background: url('https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1200&q=80') center/cover no-repeat; }
        .journey-card .content { padding: 1.4rem 1.5rem 1.5rem; background: rgba(255,255,255,0.12); }
        .journey-card h4 { font-family: 'Cormorant Garamond', serif; font-size: clamp(2.2rem, 3vw, 3rem); margin: 0; letter-spacing: -0.04em; color: var(--text); }
        .journey-card p { margin: 0.8rem 0 1.2rem; color: var(--muted); line-height: 1.5; }
        .journey-card .cta { display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; border: 1px solid rgba(12, 78, 88, 0.75); background: rgba(17,94,88,0.05); color: var(--text); padding: 0.7rem 1.2rem; text-transform: uppercase; letter-spacing: 0.08em; font-size: 0.74rem; font-weight: 700; text-decoration: none; }
        .footer-strip { max-width: 1220px; margin: 0 auto; padding: 2rem 0 2.8rem; display: flex; align-items: end; justify-content: space-between; gap: 1rem; border-top: 1px solid rgba(18,60,58,0.18); }
        .footer-title { margin: 0; font-family: 'Cormorant Garamond', serif; font-size: clamp(2.1rem, 3vw, 3.1rem); letter-spacing: -0.04em; font-weight: 500; color: var(--text); }
        .footer-sub { margin-top: 0.45rem; color: var(--muted); font-size: 0.85rem; }
        .footer-links { display: flex; align-items: center; gap: 1.4rem; color: var(--muted); font-size: 0.82rem; }
        .footer-links a { color: var(--muted); text-decoration: none; }
        @media (max-width: 1040px) { .hero { padding-left: 1.4rem; padding-right: 1.4rem; } .content-wrap, .listing-head, .type-toolbar, .room-grid, .journey, .footer-strip { max-width: calc(100% - 1.8rem); } .room-grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 760px) { .topbar { flex-wrap: wrap; gap: 0.8rem; padding-inline: 1rem; } .top-nav { width: 100%; justify-content: flex-end; flex-wrap: wrap; } .hero { min-height: 500px; padding-top: 3rem; } .listing-head, .footer-strip { flex-direction: column; align-items: flex-start; } .room-grid, .journey { grid-template-columns: 1fr; } .selector { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="page-shell">
        <header class="topbar">
            <a href="#" class="brand" aria-label="Hotel Pacific Reef">
                <span class="brand-mark" aria-hidden="true"></span>
                <span>Hotel Pacific Reef</span>
            </a>
            <nav class="top-nav" aria-label="Navegación principal">
                <a href="#">Destinos</a>
                <a href="#">Hoteles</a>
                <a href="#">Inspiración</a>
                <a href="#" class="cta">Mis reservas</a>
            </nav>
        </header>

        <main>
            <section class="hero">
                <div class="hero-inner">
                    <span class="hero-tag">Colección premium · 38 hoteles</span>
                    <h1>Estancias que merecen ser recordadas.</h1>
                    <p class="hero-copy">Arquitectura con historia, paisajes extraordinarios y un servicio que anticipa cada detalle.</p>
                    <div class="chip-row">
                        <span class="chip"><span class="check"></span> Mejora de habitación</span>
                        <span class="chip"><span class="check"></span> Desayuno incluido</span>
                        <span class="chip"><span class="check"></span> Atención prioritaria</span>
                    </div>
                </div>
            </section>

            <div class="content-wrap">
                <div class="selector" aria-label="Selecciona tipo de alojamiento">
                    <button class="selector-option active" type="button" data-filter="premium">
                        <span class="icon">◌</span>
                        <span>Premium</span>
                    </button>
                    <button class="selector-option" type="button" data-filter="economica">
                        <span class="icon">⌂</span>
                        <span>Económica</span>
                    </button>
                </div>

                <div class="listing-head">
                    <div>
                        <h2 class="listing-title">Nuestra selección Premium</h2>
                        <div class="listing-sub"><?php echo $habitaciones->num_rows; ?> alojamientos seleccionados uno a uno</div>
                    </div>
                    <div class="sort">Ordenar: Recomendados ▾</div>
                </div>

                <div class="type-toolbar" aria-label="Filtros">
                    <span class="pill">Fechas: 12-15 oct</span>
                    <span class="pill">2 huéspedes</span>
                    <span class="pill">Filtros</span>
                </div>

                <div class="room-grid" id="room-list">
                    <?php if ($habitaciones && $habitaciones->num_rows > 0): ?>
                        <?php while ($habitacion = $habitaciones->fetch_assoc()): ?>
                            <?php $tipo = getRoomType($habitacion['categoria'] ?? 'Turista'); ?>
                            <?php $precio = (float) ($habitacion['valorDiario'] ?? 0); ?>
                            <article class="room-card" data-type="<?php echo htmlspecialchars($tipo); ?>">
                                <div class="room-image" style="background-image: url('<?php echo htmlspecialchars($imagenes[$habitacion['categoria']] ?? $imagenes['Turista']); ?>');"></div>
                                <div class="room-card_body">
                                    <div class="room-meta">
                                        <div class="room-price"><?php echo htmlspecialchars(formatPrice($precio)); ?></div>
                                        <div class="rating">★ 4,5</div>
                                    </div>
                                    <div class="room-num"><?php echo htmlspecialchars($habitacion['numero']); ?></div>
                                    <div class="room-little">
                                        <div class="price-tag">
                                            <strong><?php echo htmlspecialchars(formatPrice($precio)); ?></strong>
                                            <span>por noche · tasas incluidas</span>
                                        </div>
                                        <button class="reserve-btn" type="button">Ver habitación</button>
                                    </div>
                                </div>
                            </article>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p>No hay habitaciones disponibles en este momento.</p>
                    <?php endif; ?>
                </div>

                <section class="journey" aria-label="Cómo quieres vivir tu próxima estancia">
                    <div class="experience">
                        <span class="eyebrow">Colección signature</span>
                        <h3>Premium</h3>
                        <p>Hoteles con alma, diseño excepcional y atenciones que transforman cada noche.</p>
                        <button class="cta" type="button">Explorar premium</button>
                    </div>
                    <div class="journey-card">
                        <div class="image" aria-label="Habitación económica"></div>
                        <div class="content">
                            <h4>Económica</h4>
                            <p>Estancias inteligentes, actuales y bien ubicadas para aprovechar más cada viaje.</p>
                            <a class="cta" href="#">Explorar económica</a>
                        </div>
                    </div>
                </section>

                <footer class="footer-strip">
                    <div>
                        <h3 class="footer-title">Viajar bien empieza eligiéndonos.</h3>
                        <div class="footer-sub">Precios transparentes, asistencia local y reserva segura en cada estancia.</div>
                    </div>
                    <div class="footer-links">
                        <a href="#">Ayuda 24/7</a>
                        <a href="#">Condiciones</a>
                        <a href="#">Privacidad</a>
                    </div>
                </footer>
            </div>
        </main>
    </div>

    <script>
        const selectorButtons = document.querySelectorAll('.selector-option');
        const cards = document.querySelectorAll('.room-card');

        selectorButtons.forEach(button => {
            button.addEventListener('click', () => {
                selectorButtons.forEach(btn => btn.classList.toggle('active', btn === button));
                const filter = button.dataset.filter;
                const title = document.querySelector('.listing-title');
                title.textContent = filter === 'premium' ? 'Nuestra selección Premium' : 'Nuestra selección Económica';

                cards.forEach(card => {
                    const match = filter === 'premium' ? card.dataset.type === 'premium' : card.dataset.type === 'economica';
                    card.style.display = match ? 'block' : 'none';
                });
            });
        });
    </script>
</body>
</html>
