
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `sabb`
--

-- --------------------------------------------------------

--
-- Table structure for table `securityAssets`
--

CREATE TABLE IF NOT EXISTS `securityAssets` (
  `id` int(11) NOT NULL,
  `clientName` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `clientId` varchar(300) COLLATE utf8_unicode_ci NOT NULL,
  `clientSecret` varchar(300) COLLATE utf8_unicode_ci NOT NULL,
  `accessToken` varchar(300) COLLATE utf8_unicode_ci NOT NULL,
  `expiresIn` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `refreshToken` varchar(300) COLLATE utf8_unicode_ci NOT NULL,
  `refreshExpiresIn` varchar(300) COLLATE utf8_unicode_ci NOT NULL,
  `lastRefresh` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `securityAssets`
--
ALTER TABLE `securityAssets`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `securityAssets`
--
ALTER TABLE `securityAssets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=3;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;