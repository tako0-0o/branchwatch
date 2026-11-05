</main>
<script src="<?= e(url('assets/js/common.js')) ?>"></script>
<?php foreach (($scripts ?? []) as $js): ?>
<script src="<?= e(url('assets/js/' . $js)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
