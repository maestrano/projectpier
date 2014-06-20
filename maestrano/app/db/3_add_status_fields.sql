--
-- ADD status FIELDS
--

ALTER TABLE  `pp088_projects` ADD  `status` VARCHAR( 255 ) NOT NULL DEFAULT  'ACTIVE';
ALTER TABLE  `pp088_project_users` ADD  `status` VARCHAR( 255 ) NOT NULL DEFAULT  'ACTIVE';
ALTER TABLE  `pp088_project_milestones` ADD  `status` VARCHAR( 255 ) NOT NULL DEFAULT  'ACTIVE';
ALTER TABLE  `pp088_project_task_lists` ADD  `status` VARCHAR( 255 ) NOT NULL DEFAULT  'ACTIVE';
ALTER TABLE  `pp088_project_tasks` ADD  `status` VARCHAR( 255 ) NOT NULL DEFAULT  'ACTIVE';

--
-- ADD mno_status FIELDS
--

ALTER TABLE  `pp088_projects` ADD  `mno_status` VARCHAR( 255 ) DEFAULT NULL;
ALTER TABLE  `pp088_project_users` ADD  `mno_status` VARCHAR( 255 ) DEFAULT NULL;
ALTER TABLE  `pp088_project_milestones` ADD  `mno_status` VARCHAR( 255 ) DEFAULT NULL;
ALTER TABLE  `pp088_project_task_lists` ADD  `mno_status` VARCHAR( 255 ) DEFAULT NULL;
ALTER TABLE  `pp088_project_tasks` ADD  `mno_status` VARCHAR( 255 ) DEFAULT NULL;

--
-- CREATE TABLE `pp088_project_tasks_assignees`
--

CREATE TABLE IF NOT EXISTS `pp088_project_tasks_assignees` (
  `task_id` int(10) NOT NULL,
  `user_id` int(10) NOT NULL,
  `status` varchar(255) NOT NULL,
  UNIQUE KEY `unique_user_task` (`task_id`,`user_id`)
);

--
-- TRIGGER `export_task_assignees_delete` ON TABLE pp088_project_tasks
--

DROP TRIGGER IF EXISTS `export_task_assignees_delete`;
DELIMITER //
CREATE TRIGGER `export_task_assignees_delete` AFTER DELETE ON `pp088_project_tasks`
 FOR EACH ROW BEGIN
    IF EXISTS (SELECT * FROM pp088_project_tasks_assignees WHERE task_id=OLD.id and user_id=OLD.assigned_to_user_id) AND (OLD.assigned_to_user_id IS NOT NULL) THEN
	BEGIN
	UPDATE pp088_project_tasks_assignees SET status='INACTIVE' WHERE task_id=OLD.id and user_id=OLD.assigned_to_user_id;
	END;
    ELSE
	BEGIN
	INSERT INTO pp088_project_tasks_assignees(task_id, user_id, status) VALUES (OLD.id, OLD.assigned_to_user_id, 'INACTIVE');
	END;
    END IF;
END
//
DELIMITER ;

--
-- TRIGGER `export_task_assignees_insert` ON TABLE pp088_project_tasks
--

DROP TRIGGER IF EXISTS `export_task_assignees_insert`;
DELIMITER //
CREATE TRIGGER `export_task_assignees_insert` AFTER INSERT ON `pp088_project_tasks`
 FOR EACH ROW BEGIN
    IF EXISTS (SELECT * FROM pp088_project_tasks_assignees WHERE task_id=NEW.id and user_id=NEW.assigned_to_user_id) AND (NEW.assigned_to_user_id IS NOT NULL) THEN 
	BEGIN
	UPDATE pp088_project_tasks_assignees SET status='ACTIVE' WHERE task_id=NEW.id and user_id=NEW.assigned_to_user_id;
	END;
    ELSE
	BEGIN
	INSERT INTO pp088_project_tasks_assignees(task_id, user_id, status) VALUES (NEW.id, NEW.assigned_to_user_id, 'ACTIVE');
	END;
    END IF;
END
//
DELIMITER ;

--
-- TRIGGER `export_task_assignees_update` ON TABLE pp088_project_tasks
--

DROP TRIGGER IF EXISTS `export_task_assignees_update`;
DELIMITER //
CREATE TRIGGER `export_task_assignees_update` AFTER UPDATE ON `pp088_project_tasks`
 FOR EACH ROW BEGIN
  IF NOT (NEW.assigned_to_user_id <=> OLD.assigned_to_user_id) THEN BEGIN
    IF EXISTS (SELECT * FROM pp088_project_tasks_assignees WHERE task_id=OLD.id and user_id=OLD.assigned_to_user_id) AND (OLD.assigned_to_user_id IS NOT NULL) THEN
	BEGIN
	UPDATE pp088_project_tasks_assignees SET status='INACTIVE' WHERE task_id=OLD.id and user_id=OLD.assigned_to_user_id;
	END;
    ELSE
	BEGIN
	INSERT INTO pp088_project_tasks_assignees(task_id, user_id, status) VALUES (OLD.id, OLD.assigned_to_user_id, 'INACTIVE');
	END;
    END IF;
    IF EXISTS (SELECT * FROM pp088_project_tasks_assignees WHERE task_id=NEW.id and user_id=NEW.assigned_to_user_id) AND (NEW.assigned_to_user_id IS NOT NULL) THEN 
	BEGIN
	UPDATE pp088_project_tasks_assignees SET status='ACTIVE' WHERE task_id=NEW.id and user_id=NEW.assigned_to_user_id;
	END;
    ELSE
	BEGIN
	INSERT INTO pp088_project_tasks_assignees(task_id, user_id, status) VALUES (NEW.id, NEW.assigned_to_user_id, 'ACTIVE');
	END;
    END IF;
  END;
  END IF;
END
//
DELIMITER ;

--
-- CREATE TABLE `pp088_project_milestones_assignees`
--

CREATE TABLE IF NOT EXISTS `pp088_project_milestones_assignees` (
  `milestone_id` int(10) NOT NULL,
  `user_id` int(10) NOT NULL,
  `status` varchar(255) NOT NULL,
  UNIQUE KEY `unique_user_milestone` (`milestone_id`,`user_id`)
);

--
-- TRIGGER `export_milestone_assignees_delete` ON TABLE pp088_project_milestones
--

DROP TRIGGER IF EXISTS `export_milestone_assignees_delete`;
DELIMITER //
CREATE TRIGGER `export_milestone_assignees_delete` AFTER DELETE ON `pp088_project_milestones`
 FOR EACH ROW BEGIN
    IF EXISTS (SELECT * FROM pp088_project_milestones_assignees WHERE milestone_id=OLD.id and user_id=OLD.assigned_to_user_id) AND (OLD.assigned_to_user_id IS NOT NULL) THEN
	BEGIN
	UPDATE pp088_project_milestones_assignees SET status='INACTIVE' WHERE milestone_id=OLD.id and user_id=OLD.assigned_to_user_id;
	END;
    ELSE
	BEGIN
	INSERT INTO pp088_project_milestones_assignees(milestone_id, user_id, status) VALUES (OLD.id, OLD.assigned_to_user_id, 'INACTIVE');
	END;
    END IF;
END
//
DELIMITER ;

--
-- TRIGGER `export_milestone_assignees_insert` ON TABLE pp088_project_milestones
--

DROP TRIGGER IF EXISTS `export_milestone_assignees_insert`;
DELIMITER //
CREATE TRIGGER `export_milestone_assignees_insert` AFTER INSERT ON `pp088_project_milestones`
 FOR EACH ROW BEGIN
    IF EXISTS (SELECT * FROM pp088_project_milestones_assignees WHERE milestone_id=NEW.id and user_id=NEW.assigned_to_user_id) AND (NEW.assigned_to_user_id IS NOT NULL) THEN 
	BEGIN
	UPDATE pp088_project_milestones_assignees SET status='ACTIVE' WHERE milestone_id=NEW.id and user_id=NEW.assigned_to_user_id;
	END;
    ELSE
	BEGIN
	INSERT INTO pp088_project_milestones_assignees(milestone_id, user_id, status) VALUES (NEW.id, NEW.assigned_to_user_id, 'ACTIVE');
	END;
    END IF;
END
//
DELIMITER ;

--
-- TRIGGER `export_milestone_assignees_update` ON TABLE pp088_project_milestones
--

DROP TRIGGER IF EXISTS `export_milestone_assignees_update`;
DELIMITER //
CREATE TRIGGER `export_milestone_assignees_update` AFTER UPDATE ON `pp088_project_milestones`
 FOR EACH ROW BEGIN
  IF NOT (NEW.assigned_to_user_id <=> OLD.assigned_to_user_id) THEN BEGIN
    IF EXISTS (SELECT * FROM pp088_project_milestones_assignees WHERE milestone_id=OLD.id and user_id=OLD.assigned_to_user_id) AND (OLD.assigned_to_user_id IS NOT NULL) THEN
	BEGIN
	UPDATE pp088_project_milestones_assignees SET status='INACTIVE' WHERE milestone_id=OLD.id and user_id=OLD.assigned_to_user_id;
	END;
    ELSE
	BEGIN
	INSERT INTO pp088_project_milestones_assignees(milestone_id, user_id, status) VALUES (OLD.id, OLD.assigned_to_user_id, 'INACTIVE');
	END;
    END IF;
    IF EXISTS (SELECT * FROM pp088_project_milestones_assignees WHERE milestone_id=NEW.id and user_id=NEW.assigned_to_user_id) AND (NEW.assigned_to_user_id IS NOT NULL) THEN 
	BEGIN
	UPDATE pp088_project_milestones_assignees SET status='ACTIVE' WHERE milestone_id=NEW.id and user_id=NEW.assigned_to_user_id;
	END;
    ELSE
	BEGIN
	INSERT INTO pp088_project_milestones_assignees(milestone_id, user_id, status) VALUES (NEW.id, NEW.assigned_to_user_id, 'ACTIVE');
	END;
    END IF;
  END;
  END IF;
END
//
DELIMITER ;
