<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($contest['title']); ?></title>
    <style>
        body { font-family: Arial, sans-serif; }

        .entry-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 20px;
        }

        .entry-card {
            width: 220px;
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            box-shadow: 2px 2px 5px rgba(0,0,0,0.1);
        }

        .entry-card img {
            max-width: 100%;
            max-height: 200px;
            object-fit: cover;
            border-radius: 4px;
        }

        .entry-card .name {
            font-weight: bold;
            margin-top: 10px;
        }

        .entry-card .bio {
            font-size: 0.9em;
            color: #444;
        }

        .cta-wrap {
            border: 1px solid #888;
            padding: 16px;
            text-align: center;
            margin: 10px 0;
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

        .cooldown {
            color: #555;
            font-size: 0.95em;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../partials/header.php'; ?>


<?php if (!empty($entryMessage)) echo $entryMessage; ?>
<?php if (!empty($voteMessage)) echo $voteMessage; ?>

<h1><?php echo htmlspecialchars($contest['title']); ?></h1>

<?php if ($isVotingClosed): ?>
    <p style="color: red; font-weight: bold;">Voting for this contest has ended.</p>
<?php elseif (!$hasVotingStarted): ?>
    <p style="color: orange; font-weight: bold;">
        Voting will start on <?php echo date("F j, Y \\a\\t g:i A", strtotime($contest['start_date'])); ?>.
    </p>
<?php endif; ?>

<p><strong>Niche:</strong> <?php echo htmlspecialchars($contest['niche']); ?></p>
<p><strong>Voting:</strong> <?php echo htmlspecialchars($contest['voting_type']); ?></p>
<p><strong>Prize:</strong> <?php echo htmlspecialchars($contest['prize_type']); ?> - <?php echo htmlspecialchars($contest['prize_value']); ?></p>

<?php if (!empty($contest['sponsor_name'])): ?>
    <p><strong>Sponsored by:</strong>
        <?php if (!empty($contest['sponsor_url'])): ?>
            <a href="<?php echo htmlspecialchars($contest['sponsor_url']); ?>" target="_blank">
                <?php echo htmlspecialchars($contest['sponsor_name']); ?>
            </a>
        <?php else: ?>
            <?php echo htmlspecialchars($contest['sponsor_name']); ?>
        <?php endif; ?>
    </p>
<?php endif; ?>

<p><strong>Description:</strong></p>
<p><?php echo nl2br(htmlspecialchars($contest['description'])); ?></p>
<p><strong>Ends:</strong> <?php echo htmlspecialchars($contest['end_date']); ?></p>

<hr>

<?php if ($isVotingClosed): ?>
    <h2>Entry Closed</h2>
    <p style="color: gray;">This contest is no longer accepting entries.</p>
<?php else: ?>
    <h2>Submit an Entry</h2>
    <p>
        <a href="/contest/<?=
            !empty($contest['slug'])
                ? rawurlencode($contest['slug'])
                : (int)$contest['id']
        ?>/enter">Enter this contest</a>
    </p>
<?php endif; ?>

<hr>
<h2>Entries</h2>

<?php
// Safe fallbacks in case older routes didn’t set these
$__freeAvailable = isset($freeVoteAvailable) ? (bool)$freeVoteAvailable : false;
$__cooldownText  = isset($freeVoteCooldown)  ? (string)$freeVoteCooldown  : '';

// Sort entries by votes (descending) so highest votes are at the top
if (!empty($entries)) {
    usort($entries, function($a, $b) {
        return $b['vote_count'] <=> $a['vote_count'];
    });
}
?>

<?php if (empty($entries)): ?>
    <p>No entries yet. Be the first to enter!</p>
<?php else: ?>
    <div class="entry-grid">
        <?php foreach ($entries as $entry): ?>
            <?php
              $entryUrl = '/contest/' . rawurlencode($contest['slug'])
                        . '/e/' . rawurlencode($entry['slug']);
            ?>
            <div class="entry-card">
                <?php if (!empty($entry['image_path'])): ?>
                    <a href="<?= htmlspecialchars($entryUrl) ?>">
                        <img src="<?= htmlspecialchars($entry['image_path']) ?>" alt="Entry image">
                    </a>
                <?php endif; ?>

                <div class="name">
                    <a href="<?= htmlspecialchars($entryUrl) ?>">
                        <?= htmlspecialchars($entry['name']) ?>
                    </a>
                </div>

                <div class="bio"><?php echo nl2br(htmlspecialchars($entry['bio'])); ?></div>
                <div class="votes"><?php echo (int) $entry['vote_count']; ?> vote(s)</div>

                <?php if ($isVotingClosed): ?>
                    <p style="color: gray;">Voting closed</p>
                <?php elseif (!$hasVotingStarted): ?>
                    <p style="color: gray;">Voting not started</p>
                <?php else: ?>
                    <div class="cta-wrap">
                        <?php if ($__freeAvailable): ?>
                            <!-- Free vote available: show ONLY "Vote", hide Buy Votes -->
                            <?php if (empty($_SESSION['user'])): ?>
                                <?php
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
                                <form method="POST" action="/vote" style="margin-top: 0;">
                                    <input type="hidden" name="entry_id" value="<?= (int)$entry['id'] ?>">
                                    <button type="submit" class="btn">Vote</button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <!-- Free vote NOT available: show cooldown + Buy Votes -->
                            <?php if (!empty($__cooldownText)): ?>
                                <div class="cooldown">
                                    Next free daily vote available in <?= htmlspecialchars($__cooldownText); ?>.
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="/create-checkout-session.php" class="buy-vote-form" style="margin-top: 8px;">
                                <input type="hidden" name="entry_id" value="<?= (int)$entry['id'] ?>">
                                <input type="hidden" name="contest_id" value="<?= (int)$contest['id'] ?>">

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
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script src="https://js.stripe.com/v3/"></script>
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
            // Replace with your live/test publishable key as appropriate
            const stripe = Stripe('pk_test_QJ4W2BPUVHQe2p594IzUoPK200VFh2wfaa');
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

