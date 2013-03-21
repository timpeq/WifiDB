<!--
Database.inc.php, holds the database interactive functions.
Copyright (C) 2011 Phil Ferland

This program is free software; you can redistribute it and/or modify it under the terms
of the GNU General Public License as published by the Free Software Foundation; either
version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program;
if not, write to the

   Free Software Foundation, Inc.,
   59 Temple Place, Suite 330,
   Boston, MA 02111-1307 USA
-->
{include file="vistumbler_header.tpl"}
                                        <table width="90%" border="1" align="center">
                                            <tbody>
                                                <tr class="style4">
                                                    <th colspan="4">Stats for : {$wifidb_user_name}</th>
                                                </tr>
                                                <tr class="sub_head">
                                                        <th>ID</th><th>Total APs</th><th>First Import</th><th>Last Import</th>
                                                </tr>
                                                <tr class="dark">
                                                        <td>{$wifidb_user_id}</td><td><a class="links" href="{$wifidb_host_url}opt/userstats.php?func=allap&amp;user={$wifidb_user_name}">{$wifidb_user_apst}</a></td><td>{$wifidb_user_first}</td><td>{$wifidb_user_last}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <br/>
                                        <table width="90%" border="1" align="center">
                                            <tbody>
                                                <tr class="style4">
                                                    <th colspan="4">Last Import Details</th>
                                                </tr>
                                                <tr class="sub_head">
                                                    <th>ID</th><th colspan="3">Title</th>
                                                </tr>
                                                <tr class="dark">
                                                    <td align="center">{$wifidb_user_newest_id}</td><td colspan="4" align="center"><a class="links" href="{$wifidb_host_url}opt/userstats.php?func=useraplist&amp;row={$wifidb_user_newest_id}">{$wifidb_user_newest_title}</a></td>
                                                </tr>
                                                <tr class="sub_head">
                                                    <th colspan="2">Date</th><th>Total APs</th><th>Total GPS</th>
                                                </tr>
                                                <tr class="dark">
                                                    <td colspan="2" align="center">{$wifidb_user_newest_date}</td><td align="center">{$wifidb_user_newest_aps}</td><td align="center">{$wifidb_user_newest_gps}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <br>
                                        <table width="90%" border="1" align="center">
                                            <tbody>
                                                <tr class="style4">
                                                    <th colspan="4">All Previous Imports</th>
                                                </tr>
                                                <tr class="sub_head">
                                                        <th>ID</th><th>Title</th><th>Total APs</th><th>Date</th>
                                                </tr>
                                                {foreach name=outer item=wifidb_user_prev from=$wifidb_user_prev_all}
                                                <tr class="light">
                                                    <td>{$wifidb_user_prev.id}</td>
                                                    <td><a class="links" href="{$wifidb_host_url}opt/userstats.php?func=useraplist&amp;row={$wifidb_user_prev.id}">{$wifidb_user_prev.title}</a></td>
                                                    <td>{$wifidb_user_prev.aps}</td>
                                                    <td>{$wifidb_user_prev.date}</td>
                                                </tr>
                                                {/foreach}
                                            </tbody>
                                        </table>
{include file="vistumbler_footer.tpl"}