CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


/* FAST TRACK PERFORMANCE INDEXES */
/* Run these if you haven't already to speed up searching */
CREATE INDEX  idx_exeat_requests_status ON exeat_requests(status);
CREATE INDEX  idx_exeat_requests_matric_no ON exeat_requests(matric_no);
CREATE INDEX  idx_exeat_requests_status_updated_at ON exeat_requests(status, updated_at);
CREATE INDEX  idx_exeat_requests_student_id ON exeat_requests(student_id);
CREATE INDEX  idx_exeat_requests_departure_date ON exeat_requests(departure_date);
CREATE INDEX  idx_exeat_requests_return_date ON exeat_requests(return_date);

CREATE INDEX  idx_students_fname ON students(fname);
CREATE INDEX  idx_students_lname ON students(lname);
CREATE INDEX  idx_students_mname ON students(mname);
