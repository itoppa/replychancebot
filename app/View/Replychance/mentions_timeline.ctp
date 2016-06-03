<ul class="list-unstyled">
<?php foreach ($result as $v) : ?>
  <?php if (in_array($v->user->id_str, $twitterAccountIds)) : ?>
  <li><?php echo (date('Y-m-d H:i:s', strtotime($v->created_at))); ?>&nbsp;<?php echo h($v->user->screen_name); ?>&nbsp;:&nbsp;<?php echo h ($v->text); ?></li>
  <?php endif; ?>
<?php endforeach; ?>
</ul>
