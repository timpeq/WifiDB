Vistumbler WiFiDB -> Read-me
===================

  A set of PHP-scripts to manage a MySQL based Wireless Database over the web.

  Project Phase: Beta
  --------------
  http://www.wifidb.net/

	This program is free software; you can redistribute it and/or modify it under
	the terms of the GNU General Public License version 2, as published by the 
	Free Software Foundation.   This program is distributed in the hope that it 
	will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty 
	of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General 
	Public License for more details. You should have received a copy of the GNU 
	General Public License along with this program; if not, you can get it at: 
		
	Free Software Foundation, Inc.,
	51 Franklin St, Fifth Floor
	Boston, MA  02110-1301 USA
		
	Or go here:  http://www.gnu.org/licenses/gpl-2.0.txt
		
  Requirements:
		PHP 5.3 or later
			GD2 (included with PHP now)
			ZipArchive class
			SQLiteDatabase class
		MySQL 5.0 or later
		Apache 2 or later
		A Web-browser (doh!)
		
  Summary:
		WiFiDB is a Python, PHP, and MySQL based set of scripts that is intended to manage 
		Wireless Access points found with the Vistumber Wireless scanning software  
		
		Note: Python code is not yet in the Github repo since I have just started to learn Python, 
		but it is going to be uploaded to the repo very very soon. Python is going to be replacing 
		everything in the wifidb/tools folder.
		
  Installation:
		NOTE: If you are using Linux, you must chown & chgrp the wifidb folder, to the user 
		that you have apache or what ever HTTP server you are using (This is so that 
		the installer can create the config file, and to generate the graph images 
		and export KML files)
		
		Go to /[WiFiDB Path]/install/ 
		Or [WiFiDB Path]/upgrade/
		If you are upgrading
		and follow the steps, please read the notes beforehand at /[WiFiDB Path]/install/notes.html or /[WiFiDB Path]/upgrade/notes.php.
		The reset.sql file is a database reset script, mostly for advanced users, it basically drops all the DB's and re-creates them - use at your own risk
		
  Tools Directory
		Further information is in the readme.txt file in the tools folder.
		
  Change Log:
		/[WiFiDB Path]/ver.php
  Support:
		Go to the Random Intervals section of these forums http://forum.techidiots.net/forum/
		
    Enjoy!
        The RanInt Dev team
		-PFerland
