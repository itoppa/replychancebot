<?php if (!isset($screenName)) : ?>
<ul class="list-unstyled">
  <?php foreach ($twitterAccounts as $twitterAccount) : ?>
  <li><a href="/app2/replychance/execute?screen_name=<?php echo ($twitterAccount['TwitterAccount']['screen_name']); ?>"><?php echo ($twitterAccount['TwitterAccount']['screen_name']); ?></a></li>
  <?php endforeach; ?>
  <li><a href="/app2/replychance/execute?screen_name=<?php echo $twitterScreenName2; ?>"><?php echo $twitterScreenName2; ?></a></li>
</ul>
<?php else: ?>
  <p><?php echo $screenName; ?></p>
  <form id="form" name="form" action="/app2/replychance/statuses_update" method="POST">
    <div class="row">
      <p class="col-xs-12 col-md-8"><textarea id="t_text" name="data[t_text]" class="form-control" rows="5"></textarea></p>
      <p class="col-xs-12 col-md-4 text-center"><button type="submit" id="t_submit" class="btn btn-default btn-sm" disabled>reply</button></p>
    </div>
    <input type="hidden" id="t_id" name="data[t_id]" value="" />
    <input type="hidden" id="t_screen_name" name="data[t_screen_name]" value="<?php echo $screenName; ?>" />
  </form>
  <?php if ($replyDatas) : ?>
  <p>replyData</p>
  <ul class="list-unstyled">
    <?php foreach ($replyDatas as $replyData) : ?>
    <li>
      <?php echo date('Y-m-d H:i:s', strtotime($replyData['created_at'])); ?> : <?php echo $replyData['text']; ?>&nbsp;
      <button class="btn btn-default btn-xs js_select_reply" data-t_id="<?php echo $replyData['id']; ?>">select</button>
    </li><br />
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
  <?php if ($datas) : ?>
  <p>data</p>
  <ul class="list-unstyled">
    <?php foreach ($datas as $data) : ?>
    <li>
      <?php echo date('Y-m-d H:i:s', strtotime($data['created_at'])); ?> : <?php echo $data['text']; ?>&nbsp;
      <button class="btn btn-default btn-xs js_select_reply" data-t_id="<?php echo $data['id']; ?>">select</button>
    </li><br />
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
<?php endif; ?>
<script>
$(function(){
  $('.js_select_reply').on('click', function(){
    $('#t_id').val($(this).data('t_id'));
    $('ul li').removeClass('bg-info');
    $(this).parent().addClass('bg-info');
  });

  $('#t_text').on('change keyup keydown', function(){
    if ($(this).val()) {
      $('#t_submit').removeAttr('disabled');
    } else {
      $('#t_submit').attr('disabled', true);
    }
  });
});
</script>