<?php
/**
 * BillTech
 *
 * @author Michał Kaciuba <michal@billtech.pl>
 */

class BillTechInitHandler
{

	public function modulesBillTech($modules_dirs)
	{
		$plugin_modules = PLUGINS_DIR .
			DIRECTORY_SEPARATOR .
			BillTech::PLUGIN_DIRECTORY_NAME .
			DIRECTORY_SEPARATOR . 'modules';
		array_unshift($modules_dirs, $plugin_modules);
		return $modules_dirs;
	}

	public function menuBillTech($menu)
	{
		array_push($menu['finances']['submenu'], array(
			'name' => 'Płatności BillTech',
			'link' => '?m=billtechpaymentlist',
			'tip' => 'Płatności BillTech',
			'prio' => 160
		));

		array_push($menu['config']['submenu'], array(
			'name' => 'BillTech',
			'link' => '?m=billtechconfig',
			'tip' => 'Konfiguracja integracji z BillTech',
			'prio' => 120
		));
		return $menu;
	}

	public function smartyBillTech(Smarty $smarty)
	{
		//$smarty->clearCompiledTemplate();
		$template_dirs = $smarty->getTemplateDir();
		$plugin_templates = PLUGINS_DIR .
			DIRECTORY_SEPARATOR .
			BillTech::PLUGIN_DIRECTORY_NAME .
			DIRECTORY_SEPARATOR . 'templates';
		array_unshift($template_dirs, $plugin_templates);
		$smarty->setTemplateDir($template_dirs);

		return $smarty;
	}
}