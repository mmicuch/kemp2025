-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: db.dw041.nameserver.sk
-- Generation Time: May 19, 2025 at 07:16 PM
-- Server version: 5.5.62-log
-- PHP Version: 8.1.32

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `baptistsk4`
--

-- --------------------------------------------------------

--
-- Table structure for table `aktivity`
--

CREATE TABLE `aktivity` (
  `id` int(11) NOT NULL,
  `nazov` varchar(100) NOT NULL,
  `den` enum('streda','stvrtok','piatok') NOT NULL,
  `kapacita` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;;

--
-- Dumping data for table `aktivity`
--

INSERT INTO `aktivity` (`id`, `nazov`, `den`, `kapacita`) VALUES
(1, 'Chválospevka', 'streda', 10),
(2, 'Chválospevka', 'stvrtok', 10),
(3, 'Chválospevka', 'piatok', 10),
(4, 'Biblický seminár', 'streda', 15),
(5, 'Biblický seminár', 'stvrtok', 15),
(6, 'Biblický seminár', 'piatok', 15),
(7, 'Umelecký ateliér', 'streda', 12),
(8, 'Umelecký ateliér', 'stvrtok', 12),
(9, 'Športy', 'streda', 15),
(10, 'Športy', 'stvrtok', 15),
(11, 'Športy', 'piatok', 15),
(12, 'Dráma', 'piatok', 8),
(13, 'Choreografia', 'stvrtok', 8),
(14, 'Tvorivé dielne', 'streda', 15),
(15, 'Tvorivé dielne', 'stvrtok', 15),
(16, 'Tvorivé dielne', 'piatok', 15);

-- --------------------------------------------------------

--
-- Table structure for table `alergie`
--

CREATE TABLE `alergie` (
  `id` int(11) NOT NULL,
  `nazov` varchar(255) NOT NULL,
  `popis` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;;

--
-- Dumping data for table `alergie`
--

INSERT INTO `alergie` (`id`, `nazov`, `popis`) VALUES
(1, 'Lepok', 'Psenica, raz, jacmen, ovos, spalda, kamut alebo ich hybridne odrody.'),
(2, 'Vajcia', 'Alergia na vajcia a vyrobky z nich.'),
(3, 'Ryby', 'Ryby a vyrobky z nich.'),
(4, 'Arašidy', 'Alergia na arasidy a vyrobky z nich.'),
(5, 'Sojové zrná', 'Sojove produkty vratane sojoveho mlieka a tofu.'),
(6, 'Mlieko', 'Alergia na mlieko, mliecne bielkoviny, mliecne vyrobky.'),
(7, 'Orechy', 'Mandle, lieskové orechy, vlasské orechy, kesu, pekanové orechy, para orechy, pistacie, makadamove orechy.'),
(8, 'Horčica', 'Horcica a vyrobky z nej.'),
(9, 'Sezamové semienka', 'Alergia na sezam a vyrobky z neho.'),
(10, 'Histamín', 'Obmedzenie potravin s vysokym obsahom histaminu.'),
(11, 'Citrusy', 'Alergia na citrusove ovocie, ako su pomarance, citrony, limetky.'),
(12, 'Penicilín', 'Alergia na antibiotikum penicilin.'),
(13, 'Ine', NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `dostupne_aktivity`
-- (See below for the actual view)
--
CREATE TABLE `dostupne_aktivity` (
`id` int(11)
,`nazov` varchar(100)
,`den` enum('streda','stvrtok','piatok')
,`kapacita` int(11)
,`obsadene` bigint(21)
,`available_spots` bigint(22)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `dostupne_ubytovanie`
-- (See below for the actual view)
--
CREATE TABLE `dostupne_ubytovanie` (
`id` int(11)
,`izba` varchar(50)
,`kapacita` int(11)
,`typ` enum('muz','zena','veduci','spolocne')
,`obsadene` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `export_data`
-- (See below for the actual view)
--
CREATE TABLE `export_data` (
`ID` int(11)
,`Meno` varchar(50)
,`Priezvisko` varchar(50)
,`Dátum narodenia` date
,`Pohlavie` varchar(4)
,`Mládež` varchar(100)
,`E-mail` varchar(100)
,`Poznámka` text
,`Nový` varchar(3)
,`Účastník` enum('taborujuci','veduci','host')
,`Ubytovanie` varchar(50)
,`Aktivity (Streda)` varchar(100)
,`Aktivity (Štvrtok)` varchar(100)
,`Aktivity (Piatok)` varchar(100)
,`Alergie` text
);

-- --------------------------------------------------------

--
-- Table structure for table `mladez`
--

CREATE TABLE `mladez` (
  `id` int(11) NOT NULL,
  `nazov` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;;

--
-- Dumping data for table `mladez`
--

INSERT INTO `mladez` (`id`, `nazov`) VALUES
(12, 'BJB Banská Bystrica'),
(13, 'BJB Bernolákovo'),
(4, 'BJB Hurbanovo'),
(11, 'BJB Košice'),
(3, 'BJB Nesvady'),
(5, 'BJB Nové Zamky'),
(8, 'BJB Palisády BA'),
(7, 'BJB Poprad'),
(10, 'BJB Prešov'),
(9, 'Klub4youtoo (BJB Ružomberok)'),
(6, 'OpenGate (BJB Lučenec)'),
(1, 'SHIFT (BJB Komárno)'),
(2, 'TeenZone (BJB Viera BA)');

-- --------------------------------------------------------

--
-- Table structure for table `os_udaje`
--

CREATE TABLE `os_udaje` (
  `id` int(11) NOT NULL,
  `meno` varchar(50) NOT NULL,
  `priezvisko` varchar(50) NOT NULL,
  `datum_narodenia` date NOT NULL,
  `pohlavie` enum('M','F') NOT NULL,
  `mladez` varchar(100) DEFAULT NULL,
  `poznamka` text,
  `mail` varchar(100) NOT NULL,
  `novy` tinyint(1) DEFAULT '1',
  `ucastnik` enum('taborujuci','veduci','host') DEFAULT 'taborujuci',
  `GDPR` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;;

-- --------------------------------------------------------

--
-- Table structure for table `os_udaje_aktivity`
--

CREATE TABLE `os_udaje_aktivity` (
  `os_udaje_id` int(11) NOT NULL DEFAULT '0',
  `aktivita_id` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;;

-- --------------------------------------------------------

--
-- Table structure for table `os_udaje_alergie`
--

CREATE TABLE `os_udaje_alergie` (
  `os_udaje_id` int(11) NOT NULL DEFAULT '0',
  `alergie_id` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;;

-- --------------------------------------------------------

--
-- Table structure for table `os_udaje_ubytovanie`
--

CREATE TABLE `os_udaje_ubytovanie` (
  `os_udaje_id` int(11) NOT NULL DEFAULT '0',
  `ubytovanie_id` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;;

-- --------------------------------------------------------

--
-- Table structure for table `ubytovanie`
--

CREATE TABLE `ubytovanie` (
  `id` int(11) NOT NULL,
  `izba` varchar(50) NOT NULL,
  `kapacita` int(11) NOT NULL,
  `typ` enum('muz','zena','veduci','spolocne') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;;

--
-- Dumping data for table `ubytovanie`
--

INSERT INTO `ubytovanie` (`id`, `izba`, `kapacita`, `typ`) VALUES
(1, 'Chata', 15, 'muz'),
(2, 'Chata', 15, 'zena'),
(3, 'Špeciálna izba', 6, 'zena'),
(4, 'Kancelária', 2, 'veduci'),
(5, 'Stan/Hamak', 35, 'spolocne');

-- --------------------------------------------------------

--
-- Structure for view `dostupne_aktivity`
--
DROP TABLE IF EXISTS `dostupne_aktivity`;

CREATE ALGORITHM=UNDEFINED DEFINER=`db1846`@`%` SQL SECURITY DEFINER VIEW `dostupne_aktivity`  AS SELECT `a`.`id` AS `id`, `a`.`nazov` AS `nazov`, `a`.`den` AS `den`, `a`.`kapacita` AS `kapacita`, (select count(0) from `os_udaje_aktivity` where (`os_udaje_aktivity`.`aktivita_id` = `a`.`id`)) AS `obsadene`, (`a`.`kapacita` - (select count(0) from `os_udaje_aktivity` where (`os_udaje_aktivity`.`aktivita_id` = `a`.`id`))) AS `available_spots` FROM `aktivity` AS `a` ;

-- --------------------------------------------------------

--
-- Structure for view `dostupne_ubytovanie`
--
DROP TABLE IF EXISTS `dostupne_ubytovanie`;

CREATE ALGORITHM=UNDEFINED DEFINER=`db1846`@`%` SQL SECURITY DEFINER VIEW `dostupne_ubytovanie`  AS SELECT `u`.`id` AS `id`, `u`.`izba` AS `izba`, `u`.`kapacita` AS `kapacita`, `u`.`typ` AS `typ`, (select count(0) from `os_udaje_ubytovanie` where (`os_udaje_ubytovanie`.`ubytovanie_id` = `u`.`id`)) AS `obsadene` FROM `ubytovanie` AS `u` HAVING (`obsadene` < `kapacita`) ;

-- --------------------------------------------------------

--
-- Structure for view `export_data`
--
DROP TABLE IF EXISTS `export_data`;

CREATE ALGORITHM=UNDEFINED DEFINER=`db1846`@`%` SQL SECURITY DEFINER VIEW `export_data`  AS SELECT `ou`.`id` AS `ID`, `ou`.`meno` AS `Meno`, `ou`.`priezvisko` AS `Priezvisko`, `ou`.`datum_narodenia` AS `Dátum narodenia`, (case `ou`.`pohlavie` when 'M' then 'Muž' when 'F' then 'Žena' end) AS `Pohlavie`, coalesce(`m`.`nazov`,`ou`.`mladez`) AS `Mládež`, `ou`.`mail` AS `E-mail`, `ou`.`poznamka` AS `Poznámka`, if((`ou`.`novy` = 1),'Áno','Nie') AS `Nový`, `ou`.`ucastnik` AS `Účastník`, `u`.`izba` AS `Ubytovanie`, max((case when (`a`.`den` = 'streda') then `a`.`nazov` end)) AS `Aktivity (Streda)`, max((case when (`a`.`den` = 'stvrtok') then `a`.`nazov` end)) AS `Aktivity (Štvrtok)`, max((case when (`a`.`den` = 'piatok') then `a`.`nazov` end)) AS `Aktivity (Piatok)`, group_concat(distinct `al`.`nazov` separator ', ') AS `Alergie` FROM (((((((`os_udaje` `ou` left join `mladez` `m` on((`m`.`nazov` = `ou`.`mladez`))) left join `os_udaje_ubytovanie` `ouu` on((`ou`.`id` = `ouu`.`os_udaje_id`))) left join `ubytovanie` `u` on((`u`.`id` = `ouu`.`ubytovanie_id`))) left join `os_udaje_aktivity` `oua` on((`ou`.`id` = `oua`.`os_udaje_id`))) left join `aktivity` `a` on((`a`.`id` = `oua`.`aktivita_id`))) left join `os_udaje_alergie` `oual` on((`ou`.`id` = `oual`.`os_udaje_id`))) left join `alergie` `al` on((`al`.`id` = `oual`.`alergie_id`))) GROUP BY `ou`.`id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `aktivity`
--
ALTER TABLE `aktivity`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `alergie`
--
ALTER TABLE `alergie`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mladez`
--
ALTER TABLE `mladez`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nazov` (`nazov`);

--
-- Indexes for table `os_udaje`
--
ALTER TABLE `os_udaje`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mail` (`mail`);

--
-- Indexes for table `os_udaje_aktivity`
--
ALTER TABLE `os_udaje_aktivity`
  ADD PRIMARY KEY (`os_udaje_id`,`aktivita_id`),
  ADD KEY `aktivita_id` (`aktivita_id`);

--
-- Indexes for table `os_udaje_alergie`
--
ALTER TABLE `os_udaje_alergie`
  ADD PRIMARY KEY (`os_udaje_id`,`alergie_id`),
  ADD KEY `alergie_id` (`alergie_id`);

--
-- Indexes for table `os_udaje_ubytovanie`
--
ALTER TABLE `os_udaje_ubytovanie`
  ADD PRIMARY KEY (`os_udaje_id`,`ubytovanie_id`),
  ADD KEY `ubytovanie_id` (`ubytovanie_id`);

--
-- Indexes for table `ubytovanie`
--
ALTER TABLE `ubytovanie`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `aktivity`
--
ALTER TABLE `aktivity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `alergie`
--
ALTER TABLE `alergie`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `mladez`
--
ALTER TABLE `mladez`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `os_udaje`
--
ALTER TABLE `os_udaje`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=0;

--
-- AUTO_INCREMENT for table `ubytovanie`
--
ALTER TABLE `ubytovanie`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `os_udaje_aktivity`
--
ALTER TABLE `os_udaje_aktivity`
  ADD CONSTRAINT `os_udaje_aktivity_ibfk_1` FOREIGN KEY (`os_udaje_id`) REFERENCES `os_udaje` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `os_udaje_aktivity_ibfk_2` FOREIGN KEY (`aktivita_id`) REFERENCES `aktivity` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `os_udaje_alergie`
--
ALTER TABLE `os_udaje_alergie`
  ADD CONSTRAINT `os_udaje_alergie_ibfk_1` FOREIGN KEY (`os_udaje_id`) REFERENCES `os_udaje` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `os_udaje_alergie_ibfk_2` FOREIGN KEY (`alergie_id`) REFERENCES `alergie` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `os_udaje_ubytovanie`
--
ALTER TABLE `os_udaje_ubytovanie`
  ADD CONSTRAINT `os_udaje_ubytovanie_ibfk_1` FOREIGN KEY (`os_udaje_id`) REFERENCES `os_udaje` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `os_udaje_ubytovanie_ibfk_2` FOREIGN KEY (`ubytovanie_id`) REFERENCES `ubytovanie` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
