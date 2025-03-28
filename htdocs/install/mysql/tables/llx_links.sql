-- ========================================================================
-- Copyright (C) 2013 Cédric Salvador <csalvador@gpcsolutions.fr>
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program. If not, see <https://www.gnu.org/licenses/>.
--
--
-- Table to store external URL links to documents
-- ========================================================================

create table llx_links
(
  rowid             INTEGER AUTO_INCREMENT PRIMARY KEY,
  entity            INTEGER DEFAULT 1 NOT NULL,     -- multi company id
  datea             DATETIME NOT NULL,              -- date start
  url               VARCHAR(255) NOT NULL,          -- link url
  label             VARCHAR(255) NOT NULL,          -- link label
  objecttype        VARCHAR(255) NOT NULL,          -- object type in Dolibarr
  objectid          INTEGER NOT NULL,
  share				varchar(128) NULL,				-- contains hash for file sharing
  share_pass		varchar(32) NULL				-- password to access the file (encoded with dolEncrypt)
)ENGINE=innodb;
