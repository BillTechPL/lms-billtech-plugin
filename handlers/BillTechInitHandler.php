<?php
/**
 * BillTech
 *
 * @author Michał Kaciuba <michal@billtech.pl>
 */

class BillTechInitHandler
{
    public function smartyBillTech(Smarty $smarty)
    {
        $template_dirs = $smarty->getTemplateDir();
        $plugin_templates = PLUGINS_DIR .
            DIRECTORY_SEPARATOR .
            BillTech::PLUGIN_DIRECTORY_NAME .
            DIRECTORY_SEPARATOR . 'templates';
        array_push($template_dirs, $plugin_templates);
        $smarty->setTemplateDir($template_dirs);

        return $smarty;
    }
}