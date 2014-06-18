<?php

function push_project_to_maestrano($local_project_id)
{
    
    error_log(__FILE__. " " . __FUNCTION__ . " local_project_id=" . $local_project_id);
    
    try {
        $maestrano = MaestranoService::getInstance();
        if (!$maestrano->isSoaEnabled() or !$maestrano->getSoaUrl()) { return; }

        $mno_proj=new MnoSoaProject();
        $mno_proj->setLocalEntityIdentifier($local_project_id);
        $mno_proj->send(null);
    } catch (Exception $ex) {
        // DO NOTHING
        error_log(__FILE__. " " . __FUNCTION__ . " error=" . $ex->getMessage());
        error_log(__FILE__. " " . __FUNCTION__ . " trace=" . json_encode($ex->getTrace()));
    }
}

?>

