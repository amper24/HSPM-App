-- База данных "Учебный отдел ВШПМ" СПбГУПТД
-- MySQL 5.7.21

/*!40101 SET NAMES utf8 */;

CREATE DATABASE IF NOT EXISTS `vshpm_edu` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE `vshpm_edu`;

-- Пользователи системы
CREATE TABLE `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'user') NOT NULL DEFAULT 'user',
  `full_name` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `users` (`username`, `password`, `role`, `full_name`) VALUES
('admin', '$2y$10$ymPDYHigW0Fr6cyeER4miu4s3EGCnml4j7x1LqnWG0tyTJpf/gMDO', 'admin', 'Администратор системы');

-- Аудитории (статические характеристики помещения)
CREATE TABLE `classrooms` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `room_number` VARCHAR(20) NOT NULL COMMENT 'Номер аудитории',
  `building` VARCHAR(5) NOT NULL COMMENT 'Корпус: Д-Джамбула, В-Вознесенский',
  `room_type` VARCHAR(50) NOT NULL COMMENT 'Тип помещения',
  `software_installed` TEXT DEFAULT NULL COMMENT 'Установленное ПО',
  `seats` INT(11) DEFAULT NULL COMMENT 'Количество посадочных мест',
  `has_projector` TINYINT(1) DEFAULT 0 COMMENT 'Наличие проектора/телевизора',
  `has_speakers` TINYINT(1) DEFAULT 0 COMMENT 'Наличие колонок',
  `computers_count` INT(11) DEFAULT 0 COMMENT 'Количество компьютеров',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_room_building` (`room_number`, `building`),
  KEY `idx_building` (`building`),
  KEY `idx_room_type` (`room_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Преподаватели
CREATE TABLE `teachers` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `last_name` VARCHAR(100) NOT NULL COMMENT 'Фамилия',
  `first_name` VARCHAR(100) NOT NULL COMMENT 'Имя',
  `middle_name` VARCHAR(100) DEFAULT NULL COMMENT 'Отчество',
  `position` VARCHAR(255) DEFAULT NULL COMMENT 'Должность',
  `degree` VARCHAR(100) DEFAULT NULL COMMENT 'Степень',
  `title` VARCHAR(100) DEFAULT NULL COMMENT 'Звание',
  `department` VARCHAR(100) DEFAULT NULL COMMENT 'Кафедра',
  `employment_type` VARCHAR(50) DEFAULT NULL COMMENT 'Форма занятости',
  `email` VARCHAR(255) DEFAULT NULL COMMENT 'Почта',
  `phone` VARCHAR(50) DEFAULT NULL COMMENT 'Телефон',
  `notes` TEXT DEFAULT NULL COMMENT 'Особые отметки',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_unique_fio` (`last_name`, `first_name`, `middle_name`),
  KEY `idx_last_name` (`last_name`),
  KEY `idx_department` (`department`),
  KEY `idx_employment_type` (`employment_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Расписание занятий (зависит от classrooms и teachers)
CREATE TABLE `schedule` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `classroom_id` INT(11) DEFAULT NULL,
  `teacher_id` INT(11) DEFAULT NULL,
  `numerator_denominator` VARCHAR(20) DEFAULT NULL COMMENT 'Числитель/знаменатель',
  `date` DATE DEFAULT NULL COMMENT 'Дата занятия',
  `day_of_week` VARCHAR(20) DEFAULT NULL COMMENT 'День недели',
  `discipline` VARCHAR(255) DEFAULT NULL COMMENT 'Дисциплина',
  `group_department` VARCHAR(100) DEFAULT NULL COMMENT 'Кафедра группы',
  `group_code` VARCHAR(50) DEFAULT NULL COMMENT 'Шифр группы',
  `teacher_department` VARCHAR(100) DEFAULT NULL COMMENT 'Кафедра преподавателя',
  `teacher_position` VARCHAR(100) DEFAULT NULL COMMENT 'Должность преподавателя',
  `examiner` VARCHAR(255) DEFAULT NULL COMMENT 'Экзаменатор',
  `exam_type` VARCHAR(20) DEFAULT NULL COMMENT 'Экзамен/консультация',
  `session_start` DATE DEFAULT NULL COMMENT 'Начало сессии',
  `session_end` DATE DEFAULT NULL COMMENT 'Конец сессии',
  `pair_number` INT(11) DEFAULT NULL COMMENT 'Номер пары',
  `time_start` TIME DEFAULT NULL COMMENT 'Время начала',
  `time_end` TIME DEFAULT NULL COMMENT 'Время окончания',
  `is_nonstandard_time` TINYINT(1) DEFAULT 0 COMMENT 'Нестандартное время',
  `lesson_type` VARCHAR(20) DEFAULT NULL COMMENT 'Вид занятия',
  `is_occupied` TINYINT(1) DEFAULT 0 COMMENT 'Занята/свободна',
  `transfer_cancel` VARCHAR(20) DEFAULT 'нет' COMMENT 'Перенос/отмена занятия',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_classroom_id` (`classroom_id`),
  KEY `idx_teacher_id` (`teacher_id`),
  KEY `idx_date` (`date`),
  KEY `idx_transfer_cancel` (`transfer_cancel`),
  KEY `idx_is_occupied` (`is_occupied`),
  UNIQUE KEY `idx_unique_schedule` (`classroom_id`, `date`, `pair_number`, `time_start`),
  CONSTRAINT `fk_schedule_classroom` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_schedule_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Программное обеспечение
CREATE TABLE `software` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `classroom_id` INT(11) DEFAULT NULL,
  `room_number` VARCHAR(20) DEFAULT NULL COMMENT 'Номер аудитории',
  `building` VARCHAR(5) DEFAULT NULL COMMENT 'Корпус',
  `name` VARCHAR(255) NOT NULL COMMENT 'Наименование ПО',
  `notes` TEXT DEFAULT NULL COMMENT 'Особые отметки',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_classroom_id` (`classroom_id`),
  KEY `idx_building` (`building`),
  UNIQUE KEY `idx_unique_software` (`room_number`, `building`, `name`),
  CONSTRAINT `fk_software_classroom` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;