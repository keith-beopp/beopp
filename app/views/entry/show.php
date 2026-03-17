<?php /** @var array $entry, $contest_ref, $freeVoteAvailable, $freeVoteCooldown, $images */ ?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($entry['name']) ?> in <?= htmlspecialchars($contest_ref['title'] ?? $contest_ref['slug']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.4; }

        .entry-wrap { max-width: 760px; margin: 20px auto; padding: 0 12px; }
        .back { margin-bottom: 12px; }
        .meta { color: #555; margin: 8px 0 16px; }

        /* Gallery */
        .gallery { max-width: 760px; margin: 0 0 16px 0; }
        .gallery .main img {
            width: 100%; max-height: 520px; object-fit: contain;
            border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,.08);
            background: #f8f8f8;
        }
        .gallery .thumbs { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
        .gallery .thumbs img {
            width: 110px; height: 110px; object-fit: cover; border-radius: 6px;
            cursor: pointer; border: 2px solid transparent; background: #f0f0f0;
        }
        .gallery .thumbs img.active { border-color: #0066cc; }

        .cta-wrap {
            border: 1px solid #888;
            padding: 16px;
            text-align: center;
            margin: 16px 0;
            border-radius: 8px;
            background: #f9f9f9;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #0066cc;
            color: #fff;
            text-decoration: none;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-success { background: #2e8b57; }

        .cooldown { color: #555; font-size: 0.95em; margin-bottom: 8px; }

        .votes { font-weight: bold; margin: 8px 0; }
        .pending { color: #a66; font-style: italic; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../partials/header.php'; ?>

<div class="entry-wrap">

    <h1><?= htmlspecialchars($entry['name']) ?></h1>
    <p class="back">
        <a href="/contest/<?= htmlspecialchars($contest_ref['slug']) ?>">&larr; Back to contest</a>
    </p>

    <?php
    // $images is provided by the controller: primary first, then sort_order
    $images = $images ?? [];
    $cover  = $images[0]['path'] ?? ($entry['image_path'] ?? null);
    ?>

    <?php if ($cover): ?>
        <div class="gallery" id="gallery">
            <div class="main">
                <img id="mainImg" src="<?= htmlspecialchars($cover) ?>" alt="<?= htmlspecialchars($entry['name']) ?>">
            </div>

            <?php if (!empty($images)): ?>
                <div class="thumbs" id="thumbs">
                    <?php foreach ($images as $i => $img): ?>
                        <img
                            src="<?= htmlspecialchars($img['path']) ?>"
                            data-src="<?= htmlspecialchars($img['path']) ?>"
                            alt="<?= htmlspecialchars($img['caption'] ?? ('Image '.($i+1))) ?>"
                            class="<?= $i === 0 ? 'active' : '' ?>"
                        >
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <p class="meta"><?= nl2br(htmlspecialchars($entry['bio'])) ?></p>
    <p class="votes"><strong>Votes:</strong> <?= (int)$entry['votes'] ?></p>

    <?php
    // Safe fallbacks in case older routes haven't set these
    $__freeAvailable = isset($freeVoteAvailable) ? (bool)$freeVoteAvailable : false;
    $__cooldownText  = isset($freeVoteCooldown)  ? (string)$freeVoteCooldown  : '';
    ?>

    <!-- Voting Interface -->
    <div class="cta-wrap">
        <?php if ($__freeAvailable): ?>
            <?php if (empty($_SESSION['user'])): ?>
                <?php
                    // Build login URL with state that returns to this entry page and preserves the vote intent
                    $statePayload = [
                        'redirect' => $_SERVER['REQUEST_URI'],
                        'entry_id' => $entry['id']
                    ];
                    $encodedState = urlencode(base64_encode(json_encode($statePayload)));
                    $loginUrl = "https://us-west-1zqoknog9t.auth.us-west-1.amazoncognito.com/oauth2/authorize" .
                        "?client_id=1jl0f5e01ujffgddl0jejco6d4" .
                        "&response_type=code" .
                        "&scope=email+openid" .
                        "&redirect_uri=https://www.beopp.com/auth/callback.php" .
                        "&state={$encodedState}";
                ?>
                <a href="<?= $loginUrl ?>" class="btn">Vote</a>
            <?php else: ?>
                <form method="post" action="/vote" style="margin: 0;">
                    <input type="hidden" name="entry_id" value="<?= (int)$entry['id'] ?>">
                    <button type="submit" class="btn">Vote</button>
                </form>
            <?php endif; ?>

        <?php else: ?>
            <?php if (!empty($__cooldownText)): ?>
                <div class="cooldown">
                    Next free daily vote available in <?= htmlspecialchars($__cooldownText); ?>.
                </div>
            <?php endif; ?>

            <form method="POST" action="/create-checkout-session.php" class="buy-vote-form" style="margin-top: 8px;">
                <input type="hidden" name="entry_id" value="<?= (int)$entry['id'] ?>">
                <input type="hidden" name="contest_id" value="<?= (int)$contest_ref['id'] ?>">

                <label>
                    Buy votes ($1 each):
                    <input type="number" name="vote_quantity" value="1" min="1" style="width: 60px; margin-left: 8px;">
                </label>

                <button type="submit" class="btn btn-success" style="margin-left: 10px;">
                    Buy Votes
                </button>
            </form>
        <?php endif; ?>
    </div>

    <!-- ShareThis BEGIN -->
    <div class="sharethis-inline-share-buttons"></div>
    <!-- ShareThis END -->

    <?php if (!$entry['is_approved']): ?>
        <p class="pending">Pending review</p>
    <?php endif; ?>

</div>

<script>
// Simple gallery switcher
(function(){
  var thumbs = document.getElementById('thumbs');
  var main   = document.getElementById('mainImg');
  if (!thumbs || !main) return;

  thumbs.addEventListener('click', function(e) {
    var t = e.target;
    if (t && t.tagName === 'IMG' && t.dataset.src) {
      main.src = t.dataset.src;
      var all = thumbs.querySelectorAll('img');
      for (var i=0;i<all.length;i++) all[i].classList.remove('active');
      t.classList.add('active');
    }
  });
})();
</script>

<script src="https://js.stripe.com/v3"></script>
<script>
document.querySelectorAll('form[action="/create-checkout-session.php"]').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        fetch(form.action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(new FormData(form))
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error("Stripe returned error:", data.error);
                alert("Payment failed: " + data.error);
                return;
            }
            if (!data.id) {
                console.error("No session ID returned:", data);
                alert("No session ID returned. Payment cannot proceed.");
                return;
            }
            const stripe = Stripe('pk_test_QJ4W2BPUVHQe2p594IzUoPK200VFh2wfaa'); // replace as needed
            return stripe.redirectToCheckout({ sessionId: data.id });
        })
        .catch(error => {
            console.error("JS fetch error:", error);
            alert("Failed to start payment.");
        });
    });
});
</script>

</body>
</html>

