
# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- image_gallery
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `image_gallery`;

CREATE TABLE `image_gallery`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `file` VARCHAR(255),
    `mobile_file` VARCHAR(255),
    `position` INTEGER,
    `disable` INTEGER,
    `group` VARCHAR(255),
    `url` VARCHAR(255),
    `link_target` VARCHAR(8),
    `limited` INTEGER,
    `start_date` DATETIME,
    `end_date` DATETIME,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_image_gallery_group_position` (`group`, `position`),
    INDEX `idx_image_gallery_disable` (`disable`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- image_gallery_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `image_gallery_i18n`;

CREATE TABLE `image_gallery_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `alt` VARCHAR(255),
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    `button_label` VARCHAR(255),
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `image_gallery_i18n_fk_2ec1b2`
        FOREIGN KEY (`id`)
        REFERENCES `image_gallery` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
