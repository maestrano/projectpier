<?php

/**
 * Mno Organization Class
 */
class MnoSoaProject extends MnoSoaBaseProject
{
    protected $_local_entity_name = "PROJECTS";
    protected $_local_project_id = null;
    protected $_local_project_owner_id = null;
    
    protected function pushProject() 
    {
        // FETCH PROJECT
        $project_query = "SELECT    id, name, description, UNIX_TIMESTAMP(created_on) * 1000 AS start_date, 
                                    status, priority, parent_id as parent, created_by_id AS project_owner, UNIX_TIMESTAMP(completed_on) * 1000 AS completed_date,
                                    mno_status
                          FROM      ".TABLE_PREFIX."projects
                          WHERE     id = '$this->_local_project_id'";
        
        $project = DB::executeOne($project_query);
        if (empty($project)) { return null; }
        $project = (object) $project;
        
        // PUSH PROJECT DETAILS
        $this->_name = $this->push_set_or_delete_value($project->name);
        $this->_description = $this->push_set_or_delete_value($project->description);
        $this->_start_date = $this->push_set_or_delete_value($project->start_date);
        $this->_completed_date = $this->push_set_or_delete_value($project->completed_date);
        $this->_status = $this->map_project_status_to_mno_format($project->status, $project->completed_date, $project->mno_status);
        $this->_priority = $this->push_set_or_delete_value($project->priority);
        
        // PUSH PROJECT PARENT
        if (!empty($project->parent)) {
            $mno_parent_project_id_obj = MnoSoaDB::getMnoIdByLocalId($project->parent, "PROJECTS", "PROJECTS");

            if (MnoSoaDB::isNewIdentifier($mno_parent_project_id_obj)) {
                push_project_to_maestrano($project->parent);
                $mno_parent_project_id_obj = MnoSoaDB::getMnoIdByLocalId($project->parent, "PROJECTS", "PROJECTS");
            }
            
            if (MnoSoaDB::isValidIdentifier($mno_parent_project_id_obj)) {
                $this->_parent = $this->push_set_or_delete_value($mno_parent_project_id_obj->_id);
            }
        }
        
        // PUSH PROJECT OWNER
        $mno_project_owner_user_id = MnoSoaDB::getMnoUserIdByLocalUserId($project->project_owner);
        if (!empty($mno_project_owner_user_id)) { $this->_project_owner = $mno_project_owner_user_id; }
        
        $mno_project_id_obj = MnoSoaDB::getMnoIdByLocalId($this->_local_project_id, "PROJECTS", "PROJECTS");
        $this->_id = (MnoSoaDB::isValidIdentifier($mno_project_id_obj)) ? $mno_project_id_obj->_id : null;
        
        MnoSoaLogger::debug("project=".json_encode($project));
    }
    
    protected function pullProject() 
    {
        // INSTANTIATE LOCAL PROJECT OBJECT
        $local_project_obj = new project();
        $local_project_obj->push_to_maestrano = false;        
        
        $local_project_id_obj = MnoSoaDB::getLocalIdByMnoId($this->_id, $this->getMnoEntityName(), $this->getLocalEntityName());
        $name = $this->pull_set_or_delete_value($this->_name);
        $description = $this->pull_set_or_delete_value($this->_description);
        $start_date = $this->map_date_to_local_format($this->_start_date);
        $completed_date = $this->map_date_to_local_format($this->_completed_date, "NOW()");
        $status_string_format = $this->pull_set_or_delete_value($this->_status);
        $status = $this->map_project_status_to_local_format($this->_status);
        $mno_status = $this->pull_set_or_delete_value($this->_status, null);
        $priority = $this->pull_set_or_delete_value($this->_priority);
        
        // PULL PARENT
        $mno_parent_id = $this->pull_set_or_delete_value($this->_parent);
        $local_parent_id = 0;
        
        if (!empty($mno_parent_id)) {
            $notification = (object) array();
            $notification->id = $mno_parent_id;
            $notification->entity = "PROJECTS";
            process_notification($notification);
            $local_parent_id_obj = MnoSoaDB::getLocalIdByMnoId($mno_parent_id, "PROJECTS", "PROJECTS");
            if (MnoSoaDB::isValidIdentifier($local_parent_id_obj)) {
                $local_parent_id = $local_parent_id_obj->_id;
            }
        }
        
        // PULL PROJECT OWNER
        $this->_local_project_owner_id = 0;
        if (!empty($this->_project_owner)) {
            $local_project_owner_id = MnoSoaDB::getLocalUserIdByMnoUserId($this->_project_owner);
            $this->_local_project_owner_id = (!empty($local_project_owner_id)) ? $local_project_owner_id : 0;
        }
        
        // PERSIST PROJECT
        if (MnoSoaDB::isValidIdentifier($local_project_id_obj)) {
            $this->_local_project_id = $local_project_id_obj->_id;
            $project_query = 
                    "UPDATE ".TABLE_PREFIX."projects 
                     SET    name='$name', description='$description', created_on=$start_date, status='$status', 
                            priority='$priority', parent_id='$local_parent_id', created_by_id='$local_project_owner_id' ";
            if ($status_string_format == "COMPLETED") { $project_query .= ", completed_on=$completed_date "; } else { $project_query .= ", completed_on='0000-00-00 00:00:00' "; }
            if ($status_string_format == "COMPLETED") { $project_query .= ", completed_by_id='{$this->_local_project_owner_id}' "; }
            if (!empty($mno_status)) { $project_query .= ", mno_status='$mno_status' "; } else { $project_query .= ", mno_status=null "; }
            $project_query .= "WHERE id='$this->_local_project_id'";
            DB::execute($project_query);
        } else if (MnoSoaDB::isNewIdentifier($local_project_id_obj)) {
            $project_query = 
                    "INSERT INTO ".TABLE_PREFIX."projects (name, description, created_on, status, priority, parent_id, created_by_id, completed_on ";
            if ($status_string_format == "COMPLETED") { $project_query .= ", completed_by_id "; }
            if (!empty($mno_status)) { $project_query .= ", mno_status "; }
            $project_query .= 
                    ") "
                  . "VALUES "
                  . "('$name', '$description', $start_date, '$status', '$priority', '$local_parent_id', '$local_project_owner_id'";
            if ($status_string_format == "COMPLETED") { $project_query .= ", $completed_date "; } else { $project_query .= ", '0000-00-00 00:00:00' "; }
            if ($status_string_format == "COMPLETED") { $project_query .= ", '{$this->_local_project_owner_id}' "; }
            if (!empty($mno_status)) { $project_query .= ", '{$mno_status}' "; } else { $project_query .= ", null "; }
            $project_query .= ")";
            DB::execute($project_query);
            $this->_local_project_id = DB::lastInsertId();
            MnoSoaDB::addIdMapEntry($this->_local_project_id, $this->getLocalEntityName(), $this->_id, $this->getMnoEntityName());
        }
    }
    
    protected function pushStakeholders() 
    {       
        $assigned_query =   "SELECT user_id as id, status
                             FROM ".TABLE_PREFIX."project_users
                             WHERE project_id = '$this->_local_project_id'";
        MnoSoaLogger::debug("sql=".$assigned_query);
        $local_stakeholders = DB::executeAll($assigned_query);
        
        foreach ($local_stakeholders as $local_stakeholder) {
            MnoSoaLogger::debug("local_stakeholder=".json_encode($local_stakeholder));
            $mno_stakeholder_id = MnoSoaDB::getMnoUserIdByLocalUserId($local_stakeholder['id']);
            $mno_stakeholder_status = $this->pull_set_or_delete_value($local_stakeholder['status']);
            MnoSoaLogger::debug("mno_stakeholder_id=$mno_stakeholder_id mno_stakeholder_status=$mno_stakeholder_status");
            if (empty($mno_stakeholder_id) || $mno_stakeholder_status === null) { continue; }
            $mno_stakeholders->{$mno_stakeholder_id} = $mno_stakeholder_status;
        }
        
        $this->_stakeholders = (!empty($mno_stakeholders)) ? $mno_stakeholders : null;
    }
    
    protected function pullStakeholders() 
    {    
        // UPSERT STAKEHOLDERS
        if (!empty($this->_stakeholders)) {
            foreach ($this->_stakeholders as $mno_stakeholder_id => $mno_stakeholder_status) {
                $local_stakeholder_id = MnoSoaDB::getLocalUserIdByMnoUserId($mno_stakeholder_id);
                $local_stakeholder_status = $this->pull_set_or_delete_value($mno_stakeholder_status);
                if (empty($local_stakeholder_id) || $local_stakeholder_status===null) { continue; }
                
                MnoSoaLogger::debug("local_stakeholder_id=$local_stakeholder_id local_stakeholder_status=$local_stakeholder_status");
                
                $project_users_query = "SELECT * FROM ".TABLE_PREFIX."project_users WHERE project_id='$this->_local_project_id' and user_id='$local_stakeholder_id'";
                MnoSoaLogger::debug("project_users_query=$project_users_query");
                $project_users_record = DB::executeOne($project_users_query);
                MnoSoaLogger::debug("after project_users_query");
                if (empty($project_users_record)) {
                    $project_users_upsert_query = "INSERT INTO ".TABLE_PREFIX."project_users(project_id, user_id, note, role_id, created_on, created_by_id, status) VALUES ('$this->_local_project_id', '$local_stakeholder_id', '', 0, NOW(), 1, '$local_stakeholder_status')";
                } else {
                    $project_users_upsert_query = "UPDATE ".TABLE_PREFIX."project_users SET status='$local_stakeholder_status' WHERE project_id='$this->_local_project_id' and user_id='$local_stakeholder_id' ";
                }
                
                MnoSoaLogger::debug("project_users_upsert_query=$project_users_upsert_query");
                DB::execute($project_users_upsert_query);
                MnoSoaLogger::debug("after project_users_upsert_query");
            }
        }
    }
    
    protected function pushMilestones() 
    {      
        // FETCH MILESTONES
        $milestones_query = "SELECT id,name,description,UNIX_TIMESTAMP(created_on) * 1000 as start_date,UNIX_TIMESTAMP(due_date) * 1000 as due_date,status, UNIX_TIMESTAMP(completed_on) * 1000 AS completed_date, mno_status
                             FROM ".TABLE_PREFIX."project_milestones
                             WHERE project_id = '$this->_local_project_id'";
        $local_milestones = DB::executeAll($milestones_query);
        
        foreach ($local_milestones as $local_milestone) {
            // TRANSLATE TASKLIST LOCAL ID TO MNO ID
            $local_milestone_id = $local_milestone['id'];
            $mno_milestone_id = MnoSoaDB::getOrCreateMnoId($local_milestone_id, "MILESTONES", "MILESTONES");
            if (!MnoSoaDB::isValidIdentifier($mno_milestone_id)) { continue; }
            $mno_milestone_id = $mno_milestone_id->_id;
            $mno_milestone = (object) array();
            $mno_milestone->name = $this->push_set_or_delete_value($local_milestone['name']);
            $mno_milestone->description = $this->push_set_or_delete_value($local_milestone['description']);
            $mno_milestone->startDate = $this->push_set_or_delete_value($local_milestone['start_date']);
            $mno_milestone->dueDate = $this->push_set_or_delete_value($local_milestone['due_date']);
            $mno_milestone->completedDate = $this->push_set_or_delete_value($local_milestone['completed_date']);
            $mno_milestone->status = $this->map_project_status_to_mno_format($local_milestone['status'], $local_milestone['completed_date'], $local_milestone['mno_status']);

            // FETCH ASSIGNEES
            $milestones_assignees_query = "SELECT milestone_id, user_id, status FROM ".TABLE_PREFIX."project_milestones_assignees WHERE milestone_id=$local_milestone_id";
            $local_milestone_assignees = DB::executeAll($milestones_assignees_query);
            $mno_milestone_assignees = null;
            
            foreach ($local_milestone_assignees as $local_milestone_assignee) {
                MnoSoaLogger::debug("local_milestone_assignee=".$local_milestone_assignee['user_id']);
                $mno_milestone_assignee = MnoSoaDB::getMnoUserIdByLocalUserId($local_milestone_assignee['user_id']);
                MnoSoaLogger::debug("mno_milestone_assignee=".$mno_milestone_assignee);
                if (empty($mno_milestone_assignee)) { continue; }
                $mno_milestone_assignees->{$mno_milestone_assignee} = $local_milestone_assignee['status'];
            }
            
            if (!empty($mno_milestone_assignees)) {
                $mno_milestone->assignedTo = $mno_milestone_assignees;            
            }
            $mno_milestones->{$mno_milestone_id} = $mno_milestone;
        }
        
        $this->_milestones = (!empty($mno_milestones)) ? $mno_milestones : null;
    }
    
    protected function pullMilestones() 
    {
        // UPSERT MILESTONES
        if (!empty($this->_milestones)) {
            foreach ($this->_milestones as $mno_milestone_id => $milestone) {                
                $local_milestone_id_obj = MnoSoaDB::getLocalIdByMnoId($mno_milestone_id, "MILESTONES", "MILESTONES");
                $local_milestone_id = null;
                
                $name = $this->pull_set_or_delete_value($milestone->name);
                $description = $this->pull_set_or_delete_value($milestone->description);
                $start_date = $this->map_date_to_local_format($milestone->startDate);
                $due_date = $this->map_date_to_local_format($milestone->dueDate);
                $status = $this->map_project_status_to_local_format($milestone->status);
                $status_string_format = $this->pull_set_or_delete_value($milestone->status);
                $completed_date = $this->map_date_to_local_format($milestone->completedDate, "NOW()");
                $mno_status = $this->pull_set_or_delete_value($milestone->status, null);                
                
                if (MnoSoaDB::isValidIdentifier($local_milestone_id_obj)) {
                    MnoSoaLogger::debug("start valid identifier");
                    $local_milestone_id = $local_milestone_id_obj->_id;
                    $assigned_to_user_id = $this->map_entity_assignees_to_local_single_entity_assignee($milestone, $local_milestone_id, "project_milestones_assignees", "milestone_id");
                    
                    $milestones_query = "   UPDATE  ".TABLE_PREFIX."project_milestones 
                                            SET     name='$name', description='$description', created_on=$start_date, due_date=$due_date, 
                                                    status='$status', project_id='$this->_local_project_id', assigned_to_user_id='$assigned_to_user_id' ";
                    if ($status_string_format == "COMPLETED") { $milestones_query .= ", completed_on=$completed_date "; } else { $milestones_query .= ", completed_on='0000-00-00 00:00:00' "; }
                    if ($status_string_format == "COMPLETED") { $milestones_query .= ", completed_by_id='{$this->_local_project_owner_id}' "; }
                    if (!empty($mno_status)) { $milestones_query .= ", mno_status='$mno_status' "; } else { $milestones_query .= ", mno_status=null "; }
                    $milestones_query .= "  WHERE   id='$local_milestone_id'";
                    MnoSoaLogger::debug("update milestones_query=$milestones_query");
                    DB::execute($milestones_query);
                    MnoSoaLogger::debug("after update");
                } else if (MnoSoaDB::isNewIdentifier($local_milestone_id_obj)) {
                    MnoSoaLogger::debug("start new identifier");
                    $assigned_to_user_id = $this->find_first_active_mno_entity_assignee($milestone);
                    
                    $milestones_query = "   INSERT  ".TABLE_PREFIX."project_milestones 
                                            (   project_id, name, description, due_date, goal, assigned_to_company_id, assigned_to_user_id, 
                                                is_private, created_on, created_by_id, status, completed_on ";
                    if ($status_string_format == "COMPLETED") { $milestones_query .= ", completed_by_id "; }
                    if (!empty($mno_status)) { $milestones_query .= ", mno_status "; }
                    $milestones_query .= "  ) 
                                            VALUES 
                                            ('$this->_local_project_id', '$name', '$description', $due_date, 0, 0, '$assigned_to_user_id', 0, $start_date, 1, '$status' ";
                    if ($status_string_format == "COMPLETED") { $milestones_query .= ", $completed_date "; } else { $milestones_query .= ", '0000-00-00 00:00:00' "; }
                    if ($status_string_format == "COMPLETED") { $milestones_query .= ", '{$this->_local_project_owner_id}' "; }
                    if (!empty($mno_status)) { $milestones_query .= ", '{$mno_status}' "; } else { $milestones_query .= ", null "; }
                    $milestones_query .= "  )";
                    MnoSoaLogger::debug("insert milestones_query=$milestones_query");
                    DB::execute($milestones_query);
                    $local_milestone_id = DB::lastInsertId();
                    MnoSoaLogger::debug("after insert");
                    MnoSoaDB::addIdMapEntry($local_milestone_id, "MILESTONES", $mno_milestone_id, "MILESTONES");
                }
                
                $this->map_assignees_to_local_table($milestone, $local_milestone_id, "project_milestones_assignees", "milestone_id");
            }
        }
    }
    
    protected function pushTasklists() 
    {
        $tasklists_query = "SELECT id,name,description,UNIX_TIMESTAMP(start_date) * 1000 as start_date, UNIX_TIMESTAMP(due_date) * 1000 as due_date,milestone_id,status,priority, UNIX_TIMESTAMP(completed_on) * 1000 AS completed_date, mno_status "
                         . "FROM ".TABLE_PREFIX."project_task_lists "
                         . "WHERE project_id = '$this->_local_project_id'";
        $local_tasklists = DB::executeAll($tasklists_query);

        foreach ($local_tasklists as $local_tasklist) {
            // TRANSLATE TASKLIST LOCAL ID TO MNO ID
            $local_tasklist_id = $local_tasklist['id'];
            $mno_tasklist_id = MnoSoaDB::getOrCreateMnoId($local_tasklist_id, "TASKLISTS", "TASKLISTS");
            if (!MnoSoaDB::isValidIdentifier($mno_tasklist_id)) { continue; }
            $mno_tasklist_id = $mno_tasklist_id->_id;
            // TRANSLATE MILESTONE LOCAL ID TO MNO ID
            MnoSoaLogger::debug("local_tasklist[milestone_id]=".$local_tasklist['milestone_id']);
            $mno_milestone_id_obj = MnoSoaDB::getMnoIdByLocalId($local_tasklist['milestone_id'], "MILESTONES", "MILESTONES");
            $mno_milestone_id = (MnoSoaDB::isValidIdentifier($mno_milestone_id_obj)) ? $mno_milestone_id_obj->_id : "";
            $mno_tasklist = (object) array();
            $mno_tasklist->name = $this->push_set_or_delete_value($local_tasklist['name']);
            $mno_tasklist->description = $this->push_set_or_delete_value($local_tasklist['description']);
            $mno_tasklist->startDate = $this->push_set_or_delete_value($local_tasklist['start_date']);
            $mno_tasklist->dueDate = $this->push_set_or_delete_value($local_tasklist['due_date']);
            $mno_tasklist->completedDate = $this->push_set_or_delete_value($local_tasklist['completed_date']);
            $mno_tasklist->status = $this->map_project_status_to_mno_format($local_tasklist['status'], $local_tasklist['completed_date'], $local_tasklist['mno_status']);
            $mno_tasklist->priority = $this->push_set_or_delete_value($local_tasklist['priority']);
            $mno_tasklist->milestone = $mno_milestone_id;
            
            $mno_tasklists->{$mno_tasklist_id} = $mno_tasklist;
        }
        
        $this->_tasklists = (!empty($mno_tasklists)) ? $mno_tasklists : null;
    }
    
    protected function pullTasklists() 
    {
        // UPSERT TASKLISTS
        if (!empty($this->_tasklists)) {
            foreach($this->_tasklists as $mno_tasklist_id => $mno_tasklist) {
                $local_tasklist_id_obj = MnoSoaDB::getLocalIdByMnoId($mno_tasklist_id, "TASKLISTS", "TASKLISTS");
                $local_tasklist_id = null;

                $name = $this->pull_set_or_delete_value($mno_tasklist->name);
                $description = $this->pull_set_or_delete_value($mno_tasklist->description);
                $start_date = $this->map_date_to_local_format($mno_tasklist->startDate);
                $due_date = $this->map_date_to_local_format($mno_tasklist->dueDate);
                $completed_date = $this->map_date_to_local_format($mno_tasklist->completedDate, "NOW()");
                $status = $this->map_project_status_to_local_format($mno_tasklist->status);
                $mno_status = $this->pull_set_or_delete_value($mno_tasklist->status, null);
                $priority = $this->pull_set_or_delete_value($mno_tasklist->priority);
                $milestone = MnoSoaDB::getLocalIdByMnoId($mno_tasklist->milestone, "MILESTONES", "MILESTONES");
                $milestone_id = (MnoSoaDB::isValidIdentifier($milestone)) ? $milestone->_id : 0;
                
                if (MnoSoaDB::isValidIdentifier($local_tasklist_id_obj)) {
                    $local_tasklist_id = $local_tasklist_id_obj->_id;
                    
                    $tasklists_query = "    UPDATE  ".TABLE_PREFIX."project_task_lists 
                                            SET     name='$name', description='$description', start_date=$start_date, due_date=$due_date, status='$status', 
                                                    project_id='$this->_local_project_id', milestone_id='$milestone_id', priority='$priority' ";
                    if ($mno_status == "COMPLETED") { $tasklists_query .= ", completed_on=$completed_date "; } else { $tasklists_query .= ", completed_on='0000-00-00 00:00:00' "; }
                    if ($mno_status == "COMPLETED") { $tasklists_query .= ", completed_by_id='{$this->_local_project_owner_id}' "; }
                    if (!empty($mno_status)) { $tasklists_query .= ", mno_status='$mno_status' "; } else { $tasklists_query .= ", mno_status=null "; }
                    $tasklists_query .= "   WHERE id='$local_tasklist_id'";
                    DB::execute($tasklists_query);
                } else if (MnoSoaDB::isNewIdentifier($local_tasklist_id_obj)) {
                    $tasklists_query = "    INSERT INTO     ".TABLE_PREFIX."project_task_lists 
                                            (name, description, created_on, start_date, due_date, status, project_id, milestone_id, priority, completed_on ";
                    if ($mno_status == "COMPLETED") { $tasklists_query .= ", completed_by_id "; }
                    if (!empty($mno_status)) { $tasklists_query .= ", mno_status "; }
                    $tasklists_query .= "   ) 
                                            VALUES 
                                            ('$name', '$description', $start_date, $start_date, $due_date, '$status', '$this->_local_project_id', '$milestone_id', '$priority' ";
                    if ($mno_status == "COMPLETED") { $tasklists_query .= ", $completed_date "; } else { $tasklists_query .= ", '0000-00-00 00:00:00' "; }
                    if ($mno_status == "COMPLETED") { $tasklists_query .= ", '{$this->_local_project_owner_id}' "; }
                    if (!empty($mno_status)) { $tasklists_query .= ", '{$mno_status}' "; } else { $tasklists_query .= ", null "; }
                    $tasklists_query .= ") ";
                    DB::execute($tasklists_query);
                    $local_tasklist_id = DB::lastInsertId();
                    MnoSoaDB::addIdMapEntry($local_tasklist_id, "TASKLISTS", $mno_tasklist_id, "TASKLISTS");
                }
            }
        }
    }
    
    protected function pushTasks() 
    {
        $tasks_query =  "SELECT id,text as description,UNIX_TIMESTAMP(start_date) * 1000 as start_date,UNIX_TIMESTAMP(due_date) * 1000 as due_date,status,task_list_id as tasklist_id, UNIX_TIMESTAMP(completed_on) * 1000 AS completed_date, mno_status "
                      . "FROM ".TABLE_PREFIX."project_tasks "
                      . "WHERE task_list_id IN (SELECT id FROM ".TABLE_PREFIX."project_task_lists WHERE project_id = '$this->_local_project_id')";
        $local_tasks = DB::executeAll($tasks_query);

        foreach ($local_tasks as $local_task) {
            // TRANSLATE TASK LOCAL ID TO MNO ID
            $local_task_id = $local_task['id'];
            MnoSoaLogger::debug("local_task_id=".$local_task_id);
            $mno_task_id = MnoSoaDB::getOrCreateMnoId($local_task_id, "TASKS", "TASKS");
            if (!MnoSoaDB::isValidIdentifier($mno_task_id)) { continue; }
            $mno_task_id = $mno_task_id->_id;
            // TRANSLATE TASKLIST LOCAL ID TO MNO ID
            $mno_tasklist_id_obj = MnoSoaDB::getMnoIdByLocalId($local_task['tasklist_id'], "TASKLISTS", "TASKLISTS");
            $mno_tasklist_id = (MnoSoaDB::isValidIdentifier($mno_tasklist_id_obj)) ? $mno_tasklist_id_obj->_id : "";
            
            $mno_task = (object) array();
            $mno_task->description = $this->push_set_or_delete_value($local_task['description']);
            $mno_task->startDate = $this->push_set_or_delete_value($local_task['start_date']);
            $mno_task->dueDate = $this->push_set_or_delete_value($local_task['due_date']);
            $mno_task->completedDate = $this->push_set_or_delete_value($local_task['completed_date']);
            $mno_task->status = $this->map_project_status_to_mno_format($local_task['status'], $local_task['completed_date'], $local_task['mno_status']);
            $mno_task->tasklist = $mno_tasklist_id;
            
            // FETCH ASSIGNEES
            $tasks_assignees_query = "SELECT task_id, user_id, status FROM ".TABLE_PREFIX."project_tasks_assignees WHERE task_id=$local_task_id";
            $local_task_assignees = DB::executeAll($tasks_assignees_query);
            
            foreach ($local_task_assignees as $local_task_assignees) {
                $mno_task_assignee = MnoSoaDB::getMnoUserIdByLocalUserId($local_task_assignees['user_id']);
                if (empty($mno_task_assignee)) { continue; }
                $mno_task_assignees->{$mno_task_assignee} = $local_task_assignees['status'];
            }
            
            if (!empty($mno_task_assignees)) {
                $mno_task->assignedTo = $mno_task_assignees;
            }
            $mno_tasks->{$mno_task_id} = $mno_task;
        }
        
        $this->_tasks = $mno_tasks;
    }
    
    protected function pullTasks() 
    {
        // UPSERT TASKS
        if (!empty($this->_tasks)) {
            foreach($this->_tasks as $mno_task_id => $task) {
                if (empty($task)) { continue; }
                
                $local_task_id_obj = MnoSoaDB::getLocalIdByMnoId($mno_task_id, "TASKS", "TASKS");
                $local_task_id = null;
                
                $description = $this->pull_set_or_delete_value($task->description);
                $start_date = $this->map_date_to_local_format($task->startDate);
                $due_date = $this->map_date_to_local_format($task->dueDate);
                $completed_date = $this->map_date_to_local_format($task->completedDate, "NOW()");
                $status = $this->map_project_status_to_local_format($task->status);
                $mno_status = $this->pull_set_or_delete_value($task->status, null);
                $local_tasklist_id_obj = MnoSoaDB::getLocalIdByMnoId($task->tasklist, "TASKLISTS", "TASKLISTS");
                $local_tasklist_id = (MnoSoaDB::isValidIdentifier($local_tasklist_id_obj)) ? $local_tasklist_id_obj->_id : 0;
                
                /*
                 if ($mno_status == "COMPLETED") { $tasklists_query .= ", completed_on=$completed_date "; } else { $tasklists_query .= ", completed_on='0000-00-00 00:00:00' "; }
                    if ($mno_status == "COMPLETED") { $tasklists_query .= ", completed_by_id='{$this->_local_project_owner_id}' "; }
                 */
                
                if (MnoSoaDB::isValidIdentifier($local_task_id_obj)) {
                    $local_task_id = $local_task_id_obj->_id;
                    $assigned_to_user_id = $this->map_entity_assignees_to_local_single_entity_assignee($task, $local_task_id, "project_tasks_assignees", "task_id");
                    MnoSoaLogger::debug("mno_task_id=$mno_task_id assigned_to_user_id=$assigned_to_user_id");
                    
                    $tasklists_query = "    UPDATE  ".TABLE_PREFIX."project_tasks 
                                            SET     text='$description', created_on=$start_date, start_date=$start_date, due_date=$due_date,
                                                    status='$status', assigned_to_user_id='$assigned_to_user_id', task_list_id='$local_tasklist_id' ";
                    if ($mno_status == "COMPLETED") { $tasklists_query .= ", completed_on=$completed_date "; } else { $tasklists_query .= ", completed_on='0000-00-00 00:00:00' "; }
                    if ($mno_status == "COMPLETED") { $tasklists_query .= ", completed_by_id='{$this->_local_project_owner_id}' "; }
                    if (!empty($mno_status)) { $tasklists_query .= ", mno_status='$mno_status' "; } else { $tasklists_query .= ", mno_status=null "; }
                    $tasklists_query .= "WHERE id='$local_task_id'";
                    DB::execute($tasklists_query);
                } else if (MnoSoaDB::isNewIdentifier($local_task_id_obj)) {
                    $assigned_to_user_id = $this->find_first_active_mno_entity_assignee($task);
                    MnoSoaLogger::debug("mno_task_id=$mno_task_id assigned_to_user_id=$assigned_to_user_id");
                    
                    $tasklists_query = "INSERT INTO ".TABLE_PREFIX."project_tasks "
                                     . "(text, created_on, start_date, due_date, status, assigned_to_user_id, task_list_id, completed_on ";
                    if ($mno_status == "COMPLETED") { $tasklists_query .= ", completed_by_id "; }
                    if (!empty($mno_status)) { $tasklists_query .= ", mno_status "; }
                    $tasklists_query .= ") VALUES ('$description', $start_date, $start_date, $due_date, '$status', '$assigned_to_user_id', '$local_tasklist_id' ";
                    if ($mno_status == "COMPLETED")  { $tasklists_query .= ", $completed_date "; } else { $tasklists_query .= ", '0000-00-00 00:00:00' "; }
                    if ($mno_status == "COMPLETED") { $tasklists_query .= ", '{$this->_local_project_owner_id}' "; }
                    if (!empty($mno_status)) { $tasklists_query .= ", '{$mno_status}' "; } else { $tasklists_query .= ", null "; }
                    $tasklists_query .= ") ";
                    DB::execute($tasklists_query);
                    $local_task_id = DB::lastInsertId();
                    MnoSoaDB::addIdMapEntry($local_task_id, "TASKS", $mno_task_id, "TASKS");
                }
                
                if (empty($local_task_id)) { continue; }
                $this->map_assignees_to_local_table($task, $local_task_id, "project_tasks_assignees", "task_id");
            }
        }
    }
    
    protected function map_entity_assignees_to_local_single_entity_assignee($entity, $local_entity_id, $table_name, $local_entity_id_field_name)
    {
        MnoSoaLogger::debug("start");
        MnoSoaLogger::debug("table_name=$table_name");
        $assignees_query =  "   SELECT user_id 
                                FROM ".TABLE_PREFIX."$table_name 
                                WHERE  $local_entity_id_field_name = '$local_entity_id'";
        $local_assignee_record = DB::executeOne($assignees_query);
        MnoSoaLogger::debug("after db executeOne");
        
        if (!empty($local_assignee_record)) {
            $mno_user_id = MnoSoaDB::getMnoUserIdByLocalUserId($local_assignee['user_id']);
        }
        
        if (empty($entity->assignedTo)) { return null; }
        else if (empty($mno_user_id)) { return $this->find_first_active_mno_entity_assignee($entity); }
        else if (empty($entity->assignedTo->{$mno_user_id})) { return null; }
        else if ($entity->assignedTo->{$mno_user_id} == 'ACTIVE') { return MnoSoaDB::getLocalUserIdByMnoUserId($mno_user_id); }
        
        return $this->find_first_active_mno_entity_assignee($entity); 
    }
    
    protected function find_first_active_mno_entity_assignee($entity) {
        MnoSoaLogger::debug("start");
        foreach ($entity->assignedTo as $mno_assignee_id=>$status) {
            MnoSoaLogger::debug("mno_assignee_id=$mno_assignee_id status=$status");
            if ($status == 'ACTIVE') {
                return MnoSoaDB::getLocalUserIdByMnoUserId($mno_assignee_id);
            }
        }
        return null;
    }
    
    protected function map_assignees_to_local_table($entity, $local_entity_id, $table_name, $field_name)
    {
        MnoSoaLogger::debug("local_entity_id=$local_entity_id table_name=$table_name field_name=$field_name");
        foreach ($entity->assignedTo as $mno_assignee_id=>$status) {
            $local_user_id = MnoSoaDB::getLocalUserIdByMnoUserId($mno_assignee_id);
            if (empty($local_user_id)) { continue; }
            
            $assignees_select_query = "SELECT * FROM ".TABLE_PREFIX."$table_name WHERE $field_name='$local_entity_id' AND user_id='$local_user_id'";
            MnoSoaLogger::debug("assignees_select_query=$assignees_select_query");
            $assignees_select_record = DB::executeOne($assignees_select_query);
            
            if (empty($assignees_select_record)) {
                $assignees_upsert_query = "INSERT INTO ".TABLE_PREFIX."$table_name($field_name, user_id, status) VALUES ('$local_entity_id', '$local_user_id', '$status') ";
            } else {
                $assignees_upsert_query = "UPDATE ".TABLE_PREFIX."$table_name SET status='$status' WHERE $field_name='$local_entity_id' AND user_id='$local_user_id' ";
            }
            
            MnoSoaLogger::debug("assignees_upsert_query=$assignees_upsert_query");
            DB::execute($assignees_upsert_query);
        }
    }
        
    protected function saveLocalEntity($push_to_maestrano, $status) 
    {
        //$this->_local_entity->save();
    }
    
    public function getLocalEntityIdentifier() 
    {
        return $this->_local_project_id;
    }
    
    public function setLocalEntityIdentifier($local_identifier)
    {
        $this->_local_project_id = $local_identifier;
    }
    
    public function getLocalEntityByLocalIdentifier($local_id)
    {
        return get_project_object($local_id);
    }
    
    public function createLocalEntity()
    {
        return (object) array();
    }
    
    public function map_date_to_local_format($date, $default_date="'0000-00-00 00:00:00'")
    {
        $date_format = $this->pull_set_or_delete_value($date);
        return (!empty($date_format) && ctype_digit($date_format)) ? "FROM_UNIXTIME('" . ((string) ((int) round(intval($date_format)/1000))) . "')" : $default_date;
    }
    
    public function map_project_status_to_mno_format($status, $completed_date, $mno_status)
    {
        $status_format = $this->push_set_or_delete_value($status);
        $completed_date_format = $this->push_set_or_delete_value($completed_date);
        $mno_status_format = $this->push_set_or_delete_value($mno_status, null);
        
        if (empty($status_format)) { return null; }
        
        switch ($status_format) {
            case "INACTIVE": return "ABANDONED";
            case "ACTIVE":
                if (!empty($completed_date_format)) { return "COMPLETED"; }
                if ($mno_status_format === nukk || $mno_status_format == "INPROGRESS") { return "INPROGRESS"; }
                else if ($mno_status_format == "TODO") { return "TODO"; }
                return "INPROGRESS";
        }
        
        return null;
    }
    
    public function map_project_status_to_local_format($status)
    {
        $status_format = $this->pull_set_or_delete_value($status);
        
        if (empty($status)) { return "ACTIVE"; }
        
        switch ($status_format) {
            case "TODO": return "ACTIVE";
            case "INPROGRESS": return "ACTIVE";
            case "COMPLETED": return "ACTIVE";
            case "ABANDONED": return "INACTIVE";
        }
        
        return "ACTIVE";
    }
}

?>