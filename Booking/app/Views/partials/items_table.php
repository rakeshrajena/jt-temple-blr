<?php
/** Itemised charges. @var array $items  @var array $b (subtotal/additional/total)  */
$hasFood = (bool) array_filter($items, fn ($i) => $i['item_type'] === 'food');
?>
<table class="items">
  <caption class="sr-only">Itemised charges</caption>
  <thead><tr><th scope="col">Item</th><th scope="col" class="num">Rate</th><th scope="col" class="num">Qty</th><th scope="col" class="num">Amount</th></tr></thead>
  <tbody>
  <?php foreach ($items as $it):
      $details = $it['details'] ?? []; ?>
    <tr>
      <td>
        <strong><?= e($it['name']) ?></strong>
        <?php if (!empty($it['slot_label'])): ?><span class="sub">Time: <?= e($it['slot_label']) ?></span><?php endif; ?>
        <?php if (!empty($it['participants'])): ?><span class="sub">Participants: <?= (int) $it['participants'] ?></span><?php endif; ?>
        <?php foreach ($details as $d): ?><span class="sub"><?= e($d['label']) ?>: <?= e($d['value']) ?></span><?php endforeach; ?>
        <?php if ($it['item_type'] === 'food'): ?><span class="sub">per person</span><?php endif; ?>
      </td>
      <td class="num"><?= e(money((int) $it['unit_price_paise'])) ?></td>
      <td class="num"><?= (int) $it['quantity'] ?></td>
      <td class="num"><?= e(money((int) $it['amount_paise'])) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr><th scope="row" colspan="3">Subtotal</th><td class="num"><?= e(money((int) $sub)) ?></td></tr>
    <?php if ((int) $extra > 0): ?>
    <tr><th scope="row" colspan="3"><?= e($extraLabel ?: 'Additional charges') ?></th><td class="num"><?= e(money((int) $extra)) ?></td></tr>
    <?php endif; ?>
    <tr class="grand"><th scope="row" colspan="3">Total payable</th><td class="num"><?= e(money((int) $total)) ?></td></tr>
  </tfoot>
</table>
