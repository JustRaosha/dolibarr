-- ===================================================================
-- Copyright (C) 2002-2003 Rodolphe Quiedeville <rodolphe@quiedeville.org>
-- Copyright (C) 2005-2007 Regis Houssin        <regis.houssin@inodbox.com>
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
-- ===================================================================

create table llx_fichinterdet
(
  rowid             integer AUTO_INCREMENT PRIMARY KEY,
  fk_fichinter      integer,
  fk_parent_line    integer NULL,
  date              datetime,          -- date de la ligne d'intervention
  description       text,              -- description de la ligne d'intervention
  duree             integer,           -- duree de la ligne d'intervention
  rang              integer DEFAULT 0, -- ordre affichage sur la fiche
  extraparams		varchar(255)	   -- to stock other parameters in json format
)ENGINE=innodb;
