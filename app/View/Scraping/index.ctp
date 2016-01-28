<ul class="list-unstyled">
<?php foreach ($scrapings as $scraping) : ?>
  <li><?php echo ($scraping['Scraping']['name']); ?></li>
  <ul class="list-unstyled">
  <?php foreach ($scraping['ScrapingLog'] as $scrapingLog) : ?>
    <?php $url = sprintf('%s://%s%s', $scraping['Scraping']['protocol'], $scraping['Scraping']['domain'], $scrapingLog['url']); ?>
    <li>created:<?php echo ($scrapingLog['created']); ?>, url:<a href="<?php echo ($url); ?>" target="_blank"><?php echo ($url); ?></a></li>
  <?php endforeach; ?>
  </ul><br />
<?php endforeach; ?>
</ul>
