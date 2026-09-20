-- Selah Aesthetics Salon Appointment System
-- Database Schema
-- Run this in phpMyAdmin or MySQL CLI

CREATE DATABASE IF NOT EXISTS selah_aesthetics
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE selah_aesthetics;

-- --------------------------------------------------------
-- clients
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS clients (
  id                     INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  username               VARCHAR(50)     NOT NULL,
  email                  VARCHAR(254)    NOT NULL,
  password_hash          VARCHAR(255)    NOT NULL,
  phone                  VARCHAR(20)     NOT NULL,
  country                VARCHAR(100)    NOT NULL DEFAULT '',
  is_verified            TINYINT(1)      NOT NULL DEFAULT 0,
  verification_token     VARCHAR(64)     NULL,
  token_expires_at       DATETIME        NULL,
  failed_login_attempts  TINYINT         NOT NULL DEFAULT 0,
  locked_until           DATETIME        NULL,
  created_at             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_clients_email (email),
  UNIQUE KEY uq_clients_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- admins
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
  id                     INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  username               VARCHAR(50)     NOT NULL,
  email                  VARCHAR(254)    NOT NULL,
  password_hash          VARCHAR(255)    NOT NULL,
  failed_login_attempts  TINYINT         NOT NULL DEFAULT 0,
  locked_until           DATETIME        NULL,
  created_at             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admins_email (email),
  UNIQUE KEY uq_admins_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- services
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS services (
  id               INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  name             VARCHAR(100)     NOT NULL,
  description      TEXT             NULL,
  price            DECIMAL(10,2)    NOT NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL,
  category         VARCHAR(50)      NOT NULL,
  is_active        TINYINT(1)       NOT NULL DEFAULT 1,
  created_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- stylists
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS stylists (
  id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  name       VARCHAR(100)  NOT NULL,
  specialty  VARCHAR(100)  NOT NULL,
  bio        TEXT          NULL,
  photo_url  VARCHAR(255)  NULL,
  is_active  TINYINT(1)    NOT NULL DEFAULT 1,
  created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- schedules (time slots per stylist per date)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS schedules (
  id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  stylist_id   INT UNSIGNED  NOT NULL,
  slot_date    DATE          NOT NULL,
  start_time   TIME          NOT NULL,
  end_time     TIME          NOT NULL,
  is_available TINYINT(1)    NOT NULL DEFAULT 1,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_schedule_slot (stylist_id, slot_date, start_time),
  FOREIGN KEY (stylist_id) REFERENCES stylists(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- bookings
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS bookings (
  id                  INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  client_id           INT UNSIGNED     NOT NULL,
  service_id          INT UNSIGNED     NOT NULL,
  stylist_id          INT UNSIGNED     NOT NULL,
  schedule_id         INT UNSIGNED     NOT NULL,
  status              ENUM('Pending','Accepted','Cancelled','Completed') NOT NULL DEFAULT 'Pending',
  notes               TEXT             NULL,
  downpayment_amount  DECIMAL(10,2)    NOT NULL,
  cancelled_by        ENUM('client','admin') NULL,
  cancellation_reason TEXT             NULL,
  refund_status       ENUM('eligible','forfeited','not_applicable') NOT NULL DEFAULT 'not_applicable',
  reminder_sent       TINYINT(1)       NOT NULL DEFAULT 0,
  reminder_sent_at    DATETIME         NULL,
  created_at          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (client_id)   REFERENCES clients(id)   ON DELETE CASCADE,
  FOREIGN KEY (service_id)  REFERENCES services(id)  ON DELETE RESTRICT,
  FOREIGN KEY (stylist_id)  REFERENCES stylists(id)  ON DELETE RESTRICT,
  FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- transactions
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS transactions (
  id                     INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  booking_id             INT UNSIGNED    NOT NULL,
  client_id              INT UNSIGNED    NOT NULL,
  payment_method         ENUM('GCash','PayPal') NOT NULL,
  amount                 DECIMAL(10,2)   NOT NULL,
  status                 ENUM('Pending Verification','Paid','Rejected','Failed') NOT NULL,
  receipt_image          VARCHAR(255)    NULL,
  paypal_transaction_id  VARCHAR(100)    NULL,
  submitted_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  verified_at            DATETIME        NULL,
  verified_by            INT UNSIGNED    NULL,
  PRIMARY KEY (id),
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  FOREIGN KEY (client_id)  REFERENCES clients(id)  ON DELETE CASCADE,
  FOREIGN KEY (verified_by) REFERENCES admins(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- ratings
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS ratings (
  id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  booking_id   INT UNSIGNED  NOT NULL,
  client_id    INT UNSIGNED  NOT NULL,
  service_id   INT UNSIGNED  NOT NULL,
  stylist_id   INT UNSIGNED  NOT NULL,
  stars        TINYINT UNSIGNED NOT NULL,
  comment      TEXT          NULL,
  submitted_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rating_booking (booking_id),
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  FOREIGN KEY (client_id)  REFERENCES clients(id)  ON DELETE CASCADE,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT,
  FOREIGN KEY (stylist_id) REFERENCES stylists(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- notification_log
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS notification_log (
  id                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  booking_id        INT UNSIGNED  NULL,
  notification_type VARCHAR(50)   NOT NULL,
  recipient_email   VARCHAR(254)  NOT NULL,
  status            ENUM('sent','failed') NOT NULL,
  error_message     TEXT          NULL,
  sent_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- password_resets
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
  id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  email      VARCHAR(254)  NOT NULL,
  token      VARCHAR(64)   NOT NULL,
  expires_at DATETIME      NOT NULL,
  used       TINYINT(1)    NOT NULL DEFAULT 0,
  created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- salon_operating_hours (weekly recurring salon schedule)
-- One row per weekday; drives slot generation in booking flow.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS salon_operating_hours (
  id                    TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  day_of_week           ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  is_open               TINYINT(1)        NOT NULL DEFAULT 1,
  open_time             TIME              NOT NULL DEFAULT '09:00:00',
  close_time            TIME              NOT NULL DEFAULT '19:00:00',
  slot_duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  updated_at            DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_day (day_of_week)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO salon_operating_hours
    (day_of_week, is_open, open_time, close_time, slot_duration_minutes)
VALUES
  ('Monday',    1, '09:00:00', '19:00:00', 30),
  ('Tuesday',   1, '09:00:00', '19:00:00', 30),
  ('Wednesday', 1, '09:00:00', '19:00:00', 30),
  ('Thursday',  1, '09:00:00', '19:00:00', 30),
  ('Friday',    1, '09:00:00', '19:00:00', 30),
  ('Saturday',  1, '09:00:00', '19:00:00', 30),
  ('Sunday',    1, '10:00:00', '17:00:00', 30);

-- --------------------------------------------------------
-- stylist_weekly_schedules (per-stylist recurring schedule)
-- One row per stylist per weekday; replaces manual daily
-- time-slot creation.  Slots are generated dynamically at
-- booking time from these rows.
-- break_start / break_end are optional lunch/break windows;
-- any slot overlapping the break is automatically excluded.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS stylist_weekly_schedules (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  stylist_id  INT UNSIGNED NOT NULL,
  day_of_week ENUM('Monday','Tuesday','Wednesday',
                   'Thursday','Friday','Saturday','Sunday') NOT NULL,
  is_working  TINYINT(1)   NOT NULL DEFAULT 1,
  start_time  TIME         NOT NULL DEFAULT '09:00:00',
  break_start TIME         NULL DEFAULT NULL,
  break_end   TIME         NULL DEFAULT NULL,
  end_time    TIME         NOT NULL DEFAULT '18:00:00',
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                           ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_stylist_day (stylist_id, day_of_week),
  FOREIGN KEY (stylist_id) REFERENCES stylists(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

