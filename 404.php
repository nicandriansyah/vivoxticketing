<?php
if (!headers_sent()) http_response_code(404);
$msg = $notfound_msg ?? 'Periksa kembali link tiket yang sudah diberikan.';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Tiket Tidak Tersedia — FOAS 13</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f8f4ec;
            color: #1a0e00;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            text-align: center;
        }
        .nf-box { max-width: 420px; }
        .nf-code {
            font-family: 'Playfair Display', serif;
            font-size: 5rem;
            font-weight: 900;
            color: #c9a84c;
            line-height: 1;
        }
        .nf-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.6rem;
            font-weight: 700;
            margin: 0.5rem 0 0.75rem;
            color: #1a0800;
        }
        .nf-msg { font-size: 1.05rem; line-height: 1.6; color: #5a4a35; }
        .nf-contact {
            margin-top: 1.75rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e0d6c2;
            font-size: 1rem;
            line-height: 1.7;
            color: #5a4a35;
        }
        .nf-contact strong { color: #1a0800; }
        .nf-wa {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
            background: #25D366;
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            padding: 0.85rem 1.6rem;
            border-radius: 10px;
            font-size: 1rem;
        }
        .nf-wa:hover { background: #1da851; }
        .nf-brand { margin-top: 2rem; font-size: 0.8rem; letter-spacing: 2px; color: #9a7a55; text-transform: uppercase; }
    </style>
</head>
<body>
    <div class="nf-box">
        <div class="nf-code">404</div>
        <h1 class="nf-title">Tiket Tidak Tersedia</h1>
        <p class="nf-msg"><?= htmlspecialchars($msg) ?></p>

        <?php $waText = rawurlencode('Halo saya ada kendala dalam pemesanan ticket'); ?>
        <div class="nf-contact">
            Apabila terdapat kendala dalam pemesanan tiket dapat menghubungi<br>
            <strong>Ocin &mdash; 08098999</strong><br>
            atau klik WhatsApp berikut ini
            <br>
            <a class="nf-wa" href="https://wa.me/6281289622858?text=<?= $waText ?>" target="_blank" rel="noopener">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.867-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.345.223-.643.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                Chat WhatsApp
            </a>
        </div>

        <p class="nf-brand">Vita Voxa Choir &middot; FOAS 13</p>
    </div>
</body>
</html>
