<li onclick="window.location='<?php echo $issue->to(); ?>';">

	<div class="tag">
		<label class="label warning">Reassigned</label>
	</div>

	<div class="data">
		<a href="<?php echo $issue->to(); ?>"><?php echo $issue->title; ?></a> was reassigned to <strong><?php echo $assigned->firstname . ' ' . $assigned->lastname; ?></strong>
		by
		<strong><?php echo $user->firstname . ' ' . $user->lastname; ?></strong>

		<span class="time">
			<?php echo date('F jS \a\t g:i A', strtotime($activity->created_at)); ?>
		</span>
	</div>

	<div class="clr"></div>
</li>