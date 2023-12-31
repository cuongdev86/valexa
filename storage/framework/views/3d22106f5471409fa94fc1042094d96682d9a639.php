<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
	<head>
		<meta charset="UTF-8">
		<meta name="language" content="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title><?php echo e(__('Installation')); ?></title>
		<link rel="icon" href="<?php echo e(asset_("assets/images/favicon.png")); ?>">
		
		<!-- CSRF Token -->
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
		
		<!-- jQuery -->
		<script type="application/javascript" src="<?php echo e(asset_('assets/jquery-3.6.0.min.js')); ?>"></script>
		
		<!-- Semantic-UI -->
    <link rel="stylesheet" href="<?php echo e(asset_('assets/semantic-ui/semantic.min.2.4.2-'.locale_direction().'.css')); ?>">
    <script type="application/javascript" src="<?php echo e(asset_('assets/semantic-ui/semantic.min.2.4.2.js')); ?>"></script>

    <!-- Spacing CSS -->
		<link rel="stylesheet" href="<?php echo e(asset_('assets/css-spacing/spacing-'.locale_direction().'.css')); ?>">

		
		<script src="<?php echo e(asset_('assets/vue.min.js')); ?>"></script>

		<script type="application/javascript">
			'use strict';

			window.props = {}
		</script>

		<style>
			.main.container {
				max-width: 460px !important;
				width: 100%;
				margin: auto;
				min-height: 100vh;
		    justify-content: center;
		    align-items: center;
		    display: flex;
			}

			* {
				font-size: 1.2rem;
			}

			input, .ui.selection.dropdown, .ui.selection.dropdown.active, table, button {
			  border-radius: 1rem !important;
			}

			.step {
				display: none;
			}

			.card .content.header .step.active {
				display: flex;
				align-items: center;
			}

			.card .content.body .step.active {
				display: block;
			}

			.ui.selection.dropdown .menu {
			  border-radius: 1rem !important;
			  box-shadow: none !important;
			  border: 1px solid lightgrey !important;
			  margin-top: 1rem;
			}

			.top.attached.steps {
				border-radius: 1rem 1rem 0 0;
				overflow: hidden;
				border: none;
				border-bottom: 1px solid rgba(34,36,38,.15);
			}

			.top.attached.steps .step {
				border: none;
			}

			#app .grid {
				margin: 1rem 0;
			}

			.ui.form.grid {
				box-shadow: 0 6px 20.1px 4.9px rgba(176,191,238,.12)!important;
    		border: none !important;
    		border-radius: 1rem;
			}

			#app input[type="file"] {
				display: none;
			}

			.fields {
				width: 100%;
    		margin: 1rem .5rem !important;
			}

			.shadowless {
				box-shadow: none !important;
			}

			.bordered {
				border: 1px solid #eaeaea !important;
			}

			button[type="submit"] {
				display: block;
				float: left;
				margin-top: 1rem !important;
				width: 120px;
			}

			.button.yellow {
				background-color: #fff429 !important;
				color: #000 !important;
			}

			.button.yellow:hover {
				background-color: #fff206 !important;
				color: #000 !important;
			}
			
			.ui.card {
				max-width: 400px !important;
				width: 100%;
				box-shadow: 0 0 20px 10px #00000005;
				border-radius: 1rem !important;
			}

			.card .content.header .content {
				margin-left: .5rem;
			}

			.card .content.header .content * {
				font-size: 1.3rem;
		    line-height: 1.4;
		    font-weight: 600;
			}

			.card .content.body {
				padding: 2rem;
			}

			.card .content.footer {
				display: flex;
			}

			.card .content.footer button:first-child {
				flex: 1;
				margin-right: .5rem;
			}

			.card .content.footer button:last-child {
				flex: 1;
				margin-left: .5rem;
			}

			.table.wrapper {
				width: 100%;
				overflow-y: visible;
				overflow-x: auto;
				border-radius: 1rem;
				max-height: 300px;
			}

			.table.wrapper table {
				border: none !important;
			}

			.table thead th {
        text-align: center;
      }

      td.compatible {
			  color: #00b5ad;
			  font-weight: 600;
			}

			td.not-compatible {
			  color: #ff6b6b;
			  font-weight: 600;
			}

      .table thead th tr:first-child th {
        background: #f8f8ff;
        font-size: 1.1rem;
      }

      .table thead th tr:first-child th {
        background: #f8f8ff;
        font-size: 1.1rem;
      }


		  .table.wrapper tr td:first-child {
	      font-size: 1.2rem;
	      background: ghostwhite;
	    }
		</style>
	</head>
	<body>
		
		<div class="ui main container" id="app">
			<form class="ui fluid form card" method="post" action="<?php echo e(route('home.install_app')); ?>" enctype="multipart/form-data">
				<div class="content header">
					<div class="step" :class="{active: stepIsActive(1)}">
						<i class="cog big icon"></i>
				    <div class="content">
				      <div class="title"><?php echo e(__('Requirements')); ?></div>
				      <div class="description"><?php echo e(__('Script requirements')); ?></div>
				    </div>
					</div>

					<div class="step" :class="{active: stepIsActive(2)}">
						<i class="cog big icon"></i>
				    <div class="content">
				      <div class="title"><?php echo e(__('General')); ?></div>
				      <div class="description"><?php echo e(__('General settings')); ?></div>
				    </div>
					</div>

					<div class="step" :class="{active: stepIsActive(3)}">
						<i class="database big icon"></i>
						<div class="content">
						  <div class="title"><?php echo e(__('Database')); ?></div>
						  <div class="description"><?php echo e(__('Database settings')); ?></div>
						</div>
					</div>

					<div class="step" :class="{active: stepIsActive(4)}">
						<i class="user big icon"></i>
						<div class="content">
						  <div class="title"><?php echo e(__('Admin access')); ?></div>
						  <div class="description"><?php echo e(__('Admin account')); ?></div>
						</div>
					</div>
				</div>

				<?php if($errors->any()): ?>
				<div class="content errors">
					<?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
					<div class="ui negative fluid small message">
						<i class="times icon close"></i>
						<?php echo e($error); ?>

					</div>
			    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
				</div>
				<?php endif; ?>

				<div class="content body">
					<div class="step requirements" :class="{active: stepIsActive(1)}">
						<div class="table wrapper">
							<table class="ui celled table">
								<tbody>
									<tr>
										<td class="six column wide"><?php echo e(__('PHP version')); ?></td>
										<td>>= <?php echo e($requirements['php']['version']); ?></td>
										<td class="<?php echo e($requirements['php']['version'] <= $requirements['php']['current'] ? 'compatible' : 'not-compatible'); ?>">
											<?php echo e($requirements['php']['current']); ?>

										</td>
									</tr>
								</tbody>
							</table>
						</div>

						<div class="table wrapper mt-1">
							<table class="ui celled table">
								<tbody>
									<tr>
										<td class="six column wide"><?php echo e(__('MySQL version')); ?></td>
										<td>>= <?php echo e($requirements['mysql']['version']); ?></td>
										<td class="<?php echo e($requirements['mysql']['current']['compatible'] ? 'compatible' : 'not-compatible'); ?>">
											<?php echo e($requirements['mysql']['current']['distrib'].' '.$requirements['mysql']['current']['version']); ?>

										</td>
									</tr>
								</tbody>
							</table>
						</div>

						<div class="table wrapper mt-1">
							<table class="ui celled table">
								<thead>
									<tr>
										<th><?php echo e(__('PHP Extension')); ?></th>
										<th class="center aligned"><?php echo e(__('Enabled')); ?></th>
									</tr>
								</thead>

								<tbody>
									<?php $__currentLoopData = $requirements['php_extensions']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $name => $enabled): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
									<tr>
										<td><?php echo e(ucfirst($name)); ?></td>
										<td class="center aligned"><?php echo $enabled ? '<i class="check teal circle large outline icon mx-0"></i>' : '<i class="circle red large outline icon mx-0"></i>'; ?></td>
									</tr>
									<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
								</tbody>
							</table>
						</div>
					</div>

					<div class="step general" :class="{active: stepIsActive(2)}">
						<div class="field">
					  	<label><?php echo e(__('Name')); ?></label>
					  	<input type="text" name="site[name]" value="<?php echo e(old('site.name', request()->input('site.name'))); ?>">
					  </div>

					  <div class="field">
					  	<label><?php echo e(__('Title')); ?></label>
					  	<input type="text" name="site[title]" value="<?php echo e(old('site.title', request()->input('site.title'))); ?>">
					  </div>

					  <div class="field">
					  	<label><?php echo e(__('Items Per Page')); ?></label>
					  	<input type="number" name="site[items_per_page]" value="<?php echo e(old('site.items_per_page', request()->input('site.items_per_page'))); ?>">
					  </div>

						<div class="field">
					  	<label><?php echo e(__('Purchase code')); ?></label>
					  	<input type="text" required name="site[purchase_code]" value="<?php echo e(old('site.purchase_code', request()->input('site.purchase_code'))); ?>">
					  </div>
					</div>

					<div class="step database" :class="{active: stepIsActive(3)}">
						<div class="field">
					  	<label><?php echo e(__('Database host')); ?></label>
					  	<input type="text" required name="database[host]" value="<?php echo e(old('database.host', request()->input('database.host'))); ?>">
					  </div>

						<div class="field">
					  	<label><?php echo e(__('Database username')); ?></label>
					  	<input type="text" required name="database[username]" value="<?php echo e(old('database.username', request()->input('database.username'))); ?>">
					  </div>

					  <div class="field">
					  	<label><?php echo e(__('Database password')); ?></label>
					  	<input type="text" required name="database[password]" value="<?php echo e(old('database.password', request()->input('database.password'))); ?>">
					  </div>

					  <div class="field">
					  	<label><?php echo e(__('Database name')); ?></label>
					  	<input type="text" required name="database[database]" value="<?php echo e(old('database.database', request()->input('database.database'))); ?>">
					  </div>

					  <div class="field">
					  	<button class="ui basic big fluid button rounded mx-0" type="button" @click="testDBConnection($event)"><?php echo e(__('Test connection')); ?></button>
					  </div>
					</div>

					<div class="step admin" :class="{active: stepIsActive(4)}">
						<div class="field">
					  	<label><?php echo e(__('Admin username')); ?></label>
					  	<input type="text" required name="admin[username]" value="<?php echo e(old('admin.username', request()->input('admin.username'))); ?>">
					  </div>

					  <div class="field">
					  	<label><?php echo e(__('Admin email')); ?></label>
					  	<input type="email" required name="admin[email]" value="<?php echo e(old('admin.email', request()->input('admin.email'))); ?>">
					  </div>

					  <div class="field">
					  	<label><?php echo e(__('Admin password')); ?></label>
					  	<input type="text" required name="admin[password]" value="<?php echo e(old('admin.password', request()->input('admin.password'))); ?>">
					  </div>

					  <div class="field">
					  	<label><?php echo e(__('Admin avatar')); ?></label>
					  	<button class="ui basic big fluid button rounded" type="button" onclick="this.nextElementSibling.click()"><?php echo e(__('Browse')); ?></button>
							<input type="file" name="admin[avatar]" accept="image/*">
					  </div>
					</div>
				</div>

				<div class="content footer">
					<button class="ui large button ml-0" @click="navigateSteps(-1)" type="button" :class="{disabled: stepIsActive(1)}"><?php echo e(__('Previous')); ?></button>
					<button class="ui large button ml-auto mr-0" @click="navigateSteps(1)" type="button" v-if="step <= 3"><?php echo e(__('Next')); ?></button>
					<button class="ui large yellow button ml-auto mr-0" type="button" @click="submitForm" v-if="step == 4"><?php echo e(__('Submit')); ?></button>
				</div>
			</form>
		</div>

<script>
	'use strict';
	
	var app = new Vue({
		el: '#app',
		data: {
			step: 1
		},
		methods: {
			navigateSteps: function(number)
			{
				if(this.step + number > 4 || this.step + number < 1)
				{
					return false;
				}

				this.step += number;
			},
			stepIsActive: function(step)
			{
				return this.step == step;
			},
			submitForm: function()
			{	
				$('form').submit()
			},
			testDBConnection: function(e)
			{
				$(e.target).toggleClass('loading', true);

				var formData = $('form').serializeArray();
				var config = {
							host: null,
							username: null,
							password: null,
							database: null,
						};

				for(var i of formData)
				{
				    if(/database\[.+\]/i.test(i.name))
				    {
				        var name = i.name.replaceAll(/(database\[|\])/ig, '');
				        config[name] = i.value;
				    }
				}

				$.post('/check_database_connection', {database : config, installation: true})
				.done(function(data)
				{
					alert(data.status)
				})
				.always(function()
				{
					$(e.target).toggleClass('loading', false);
				})
			}
		}
	})

	$('.tabular.steps .step').tab()
	$('.ui.dropdown').dropdown()
	$('.ui.checkbox').checkbox()

	$(document).on('click', '.ui.message i.close', function()
	{
		$(this).closest('.ui.message').hide();
	})

	$('input').on('keypress', function(e)
	{
		if(e.keyCode === 13)
		{
			e.preventDefault();
			return false;
		}
	})
</script>
	</body>
</html><?php /**PATH D:\B_Freelance\valexa-digital-downloads-v4.2.5\resources\views/install.blade.php ENDPATH**/ ?>