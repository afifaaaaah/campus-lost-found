<?php
// admin-action.php — the single endpoint every admin button posts to.
// Four actions: decide_claim, resolve, close, delete.
//
// ITEM STATUS LIFECYCLE (the whole story in one place):
//
//   active ──claim submitted──▶ matched ──admin approves──▶ claimed ──handed over──▶ resolved
//     ▲                            │                                                     │
//     └────────admin rejects───────┘                                                     ▼
//   active ──owner/admin marks resolved──▶ resolved                      deleted 10 days later
//   active ──admin closes──▶ closed ───────────────────────────────────▶ deleted 10 days later
//   active ──nobody responds for 30 days────────────────────────────────▶ deleted

require_once 'admin-check.php';     // blocks non-admins; also loads db.php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin.php');
}
verify_csrf();

$action = post_value('action');

switch ($action) {

    // ------------------------------------------------ approve / reject a claim
    case 'decide_claim':
        $claimId  = (int) post_value('claim_id');
        $decision = post_value('decision');
        $note     = mb_substr(post_value('admin_note'), 0, 255);

        if (!in_array($decision, ['approve', 'reject'], true)) {
            flash_set('error', 'Unknown decision.');
            break;
        }

        $pdo->beginTransaction();

        // FOR UPDATE locks the row so two admins can't decide the same
        // claim at once. We only accept claims that are still 'pending'.
        $find = $pdo->prepare("SELECT * FROM claims WHERE claim_id = ? AND claim_status = 'pending' FOR UPDATE");
        $find->execute([$claimId]);
        $claim = $find->fetch();

        if (!$claim) {
            $pdo->rollBack();
            flash_set('error', 'That claim was already reviewed.');
            break;
        }

        $newClaimStatus = ($decision === 'approve') ? 'approved' : 'rejected';

        $updateClaim = $pdo->prepare(
            'UPDATE claims SET claim_status = ?, reviewed_at = NOW(), admin_note = ? WHERE claim_id = ?'
        );
        $updateClaim->execute([$newClaimStatus, $note !== '' ? $note : null, $claimId]);

        if ($decision === 'approve') {
            // Item moves on: matched -> claimed (waiting for physical hand-over)
            $updateItem = $pdo->prepare("UPDATE items SET status = 'claimed' WHERE item_id = ?");
        } else {
            // Rejected: the item goes back on the board for someone else to claim
            $updateItem = $pdo->prepare("UPDATE items SET status = 'active' WHERE item_id = ? AND status = 'matched'");
        }
        $updateItem->execute([$claim['item_id']]);

        $pdo->commit();

        flash_set('success', $decision === 'approve'
            ? 'Claim approved. Hand the item over, then click "Mark handed over".'
            : 'Claim rejected. The item is back on the board.');
        break;

    // ------------------------------------------------ mark resolved / handed over
    case 'resolve':
        $itemId = (int) post_value('item_id');
        $update = $pdo->prepare(
            "UPDATE items SET status = 'resolved', resolved_at = NOW()
             WHERE item_id = ? AND status IN ('active', 'claimed')"
        );
        $update->execute([$itemId]);
        flash_set($update->rowCount() === 1 ? 'success' : 'error',
                  $update->rowCount() === 1 ? 'Item marked as resolved.' : "That item can't be resolved right now.");
        break;

    // ------------------------------------------------ close an invalid listing
    case 'close':
        $itemId = (int) post_value('item_id');
        $update = $pdo->prepare(
            "UPDATE items SET status = 'closed', resolved_at = NOW()
             WHERE item_id = ? AND status = 'active'"
        );
        $update->execute([$itemId]);
        flash_set($update->rowCount() === 1 ? 'success' : 'error',
                  $update->rowCount() === 1 ? 'Listing closed.' : "That listing can't be closed right now.");
        break;

    // ------------------------------------------------ delete immediately
    case 'delete':
        $itemId = (int) post_value('item_id');
        $find   = $pdo->prepare('SELECT * FROM items WHERE item_id = ?');
        $find->execute([$itemId]);
        $item = $find->fetch();

        if ($item) {
            remove_item($pdo, $item);       // row + photo; its claims cascade away
            flash_set('success', 'Listing deleted.');
        } else {
            flash_set('error', 'That listing no longer exists.');
        }
        break;

    default:
        flash_set('error', 'Unknown action.');
}

redirect('admin.php');
