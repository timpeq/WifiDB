var socket, WaitingTable, ActiveTable, curentRequest;

function init() {
    connect();
    myLoop();
}

function connect() {
    var tries = 0;
    try {
        socket = new WebSocket(host);
        //console.log('WebSocket - status '+socket.readyState);

        socket.onopen    = function() {
            //console.log("Connected - status "+this.readyState);
            curentRequest = "import_waiting";
            send('import_waiting');
        };

        socket.onmessage = function(msg) {
            //console.log("Returned Message: "+msg.data);
            //clearTable();
            xmlDoc = $.parseXML( msg.data );
            $xml = $( xmlDoc );

            $search1 = $xml.find( "notice");
            //console.log($search1.length);
            if($search1.length > 0)
            {
                //return;
                //alert("Notice in "+curentRequest);
            }
            $search2 = $xml.find( "error");
            //console.log($search2.length);
            if($search2.length > 0)
            {
                //return;
                //alert("Error in "+curentRequest);
            }

            //$search2 = $xml.find("import_active");
            //console.log("Current Request: " + curentRequest);
            var CurrentTable = createTable(curentRequest, "10");
            clearTable(curentRequest);
            switch(curentRequest)
            {
                case "import_waiting":
                    parseImportWaiting(msg.data, CurrentTable);
                    curentRequest = "import_active";
                    send('import_active');
                    break;
                case "import_active":
                    parseImportActive(msg.data, CurrentTable);
                    curentRequest = "daemon_stats";
                    send('daemon_stats');
                    break;
                case "daemon_stats":
                    parseDaemonStats(msg.data, CurrentTable);
                    curentRequest = "daemon_schedule";
                    send('daemon_schedule');
                    break;
                case "daemon_schedule":
                    parseDaemonSchedule(msg.data, CurrentTable);
                    break;
                default:
                    console.log(curentRequest + "< ---- >"+msg.data);
                    break;
            }
        };
        socket.onclose   = function() {
            //console.log("Disconnected - status "+this.readyState);
            if(tries>=30)
            {
                return;
            }
            //console.log("Re-Connecting (try "+tries+" of 30)...."+this.readyState);
            reconnect();
            tries++;
        };
    }
    catch(ex){
        //console.log(ex);
    }
}

function send(msg){
    if(!msg) {
        alert("Message can not be empty");
        return;
    }
    try {
        socket.send(msg);
        console.log('Sent: '+msg);
    } catch(ex) {
        //console.log(ex);
    }
}

function quit(){
    if (socket != null) {
        //log("Goodbye!");
        socket.close();
        socket=null;
    }
}

function reconnect() {
    quit();
    connect();
}

function parseImportWaiting(response, WaitingTable) {

    var $waiting, $search1, xmlDoc, $xml, loop;
    // no matches returned
    if (response == null) {
        return false;
    } else {
        xmlDoc = $.parseXML( response ),
        $xml = $( xmlDoc ),
        $search1 = $xml.find(curentRequest);
        $waiting = $search1[0];
//        console.log($waiting);
        if ($waiting.childNodes.length > 0) {
            WaitingTable.setAttribute("bordercolor", "black");
            WaitingTable.setAttribute("border", "1");
            for (loop = 0; loop < $waiting.childNodes.length; loop++) {
                //console.log(loop);
                var file = $waiting.childNodes[loop];
                //console.log(file.childNodes[0]);

                //create row
                //WaitingTable.style.display = 'table';
                var row = document.createElement("tr");
                row.setAttribute("style", "background-color: yellow");
                CreateCell(WaitingTable, row, file.childNodes[0].innerHTML) // File ID
                CreateCell(WaitingTable, row, file.childNodes[1].innerHTML) // FileName
                CreateCell(WaitingTable, row, file.childNodes[2].innerHTML) // Username
                CreateCell(WaitingTable, row, file.childNodes[3].innerHTML) // Title
                CreateCell(WaitingTable, row, file.childNodes[4].innerHTML) // Size
                CreateCell(WaitingTable, row, file.childNodes[5].innerHTML) // Date/Time
                CreateCell(WaitingTable, row, file.childNodes[6].innerHTML) // Hash
            }
        }
        return true;
    }
}

function parseImportActive(response, ActiveTable) {

    var $active, $active, $search1, xmlDoc, $xml, loop, row, cell;
    // no matches returned
    if (response == null) {
        return false;
    } else {
        xmlDoc = $.parseXML( response ),
            $xml = $( xmlDoc ),
            $search1 = $xml.find(curentRequest);
        $active = $search1[0];
        if ($active.childNodes.length > 0) {
            //console.log("Active Response Length: "+$active.childNodes.length);
            ActiveTable.setAttribute("bordercolor", "black");
            ActiveTable.setAttribute("border", "1");
            for (loop = 0; loop < $active.childNodes.length; loop++) {
                //console.log(loop);
                var file = $active.childNodes[loop];

                console.log(file.childNodes.length);
                row = document.createElement("tr");
                row.setAttribute("style", "background-color: lime");
                CreateCell(ActiveTable, row, file.childNodes[0].innerHTML) // File ID
                CreateCell(ActiveTable, row, file.childNodes[1].innerHTML) // FileName
                CreateCell(ActiveTable, row, file.childNodes[2].innerHTML) // Username
                CreateCell(ActiveTable, row, file.childNodes[3].innerHTML) // Title
                CreateCell(ActiveTable, row, file.childNodes[4].innerHTML) // Size
                CreateCell(ActiveTable, row, file.childNodes[5].innerHTML) // Date/Time
                CreateCell(ActiveTable, row, file.childNodes[6].innerHTML) // Hash
                CreateCell(ActiveTable, row, file.childNodes[7].innerHTML) // Current AP
                CreateCell(ActiveTable, row, file.childNodes[8].innerHTML) // AP of Total APs
            }
        }
        return true;
    }
}

function parseDaemonStats(response, DaemonStatsTable) {
    if (response == null) {
        return false;
    } else {
        console.log(response);
        xmlDoc = $.parseXML(response),
            $xml = $(xmlDoc),
            $search1 = $xml.find(curentRequest);
        $Stats = $search1[0];
        console.log($Stats.childNodes.length);
        if ($Stats.childNodes.length > 0) {

//            for (loop = 0; loop < $Stats.childNodes.length; loop++) {
//                console.log(loop);
                var file = $Stats.childNodes;
//                DaemonStatsTable.style.display = 'table';

                var row = document.createElement("tr");
                row.setAttribute("colspan", "7");
                row.setAttribute("style", "background-color: yellow");

                CreateCell(DaemonStatsTable, row, file[0].innerHTML) // Node
                CreateCell(DaemonStatsTable, row, file[1].innerHTML) // PID File
                CreateCell(DaemonStatsTable, row, file[2].innerHTML) // PID
                CreateCell(DaemonStatsTable, row, file[3].innerHTML) // Time
                CreateCell(DaemonStatsTable, row, file[4].innerHTML) // Memory
                CreateCell(DaemonStatsTable, row, file[5].innerHTML) // Command
                CreateCell(DaemonStatsTable, row, file[6].innerHTML) // Updated
//            }
        }
    }
}


function parseDaemonSchedule(response, DaemonScheduleTable) {
    if (response == null) {
        return false;
    } else {
        xmlDoc = $.parseXML(response),
            $xml = $(xmlDoc),
            $search1 = $xml.find(curentRequest);
        $Stats = $search1[0];
        if ($Stats.childNodes.length > 0) {

            for (loop = 0; loop < $Stats.childNodes.length; loop++) {
                //console.log(loop);
                var file = $Stats.childNodes[loop];
                //console.log(file.childNodes[0]);

                //create row
//                DaemonScheduleTable.style.display = 'table';
                var row = document.createElement("tr");
                //row.setAttribute("width", "100%");
                row.setAttribute("colspan", "7");
                row.setAttribute("style", "background-color: yellow");
                CreateCell(DaemonScheduleTable, row, file.childNodes[0].innerHTML) // Node
                CreateCell(DaemonScheduleTable, row, file.childNodes[1].innerHTML) // Daemon
                CreateCell(DaemonScheduleTable, row, file.childNodes[2].innerHTML) // Interval
                CreateCell(DaemonScheduleTable, row, file.childNodes[3].innerHTML) // Status
                CreateCell(DaemonScheduleTable, row, file.childNodes[4].innerHTML) // NextUTC
                var date = new Date(file.childNodes[4].innerHTML + ' UTC');

                CreateCell(DaemonScheduleTable, row, date.toString()) // NextLocal
            }
        }
    }
}



function CreateCell(table, row, data) {
    if(!data)
    {
        return;
    }
    var cell;
    //console.log("Data: "+ data);
    cell = document.createElement("td");
    cell.setAttribute("align", "center")
    cell.appendChild(document.createTextNode(data));

    row.appendChild(cell);
    table.appendChild(row);
}

function createTable(tableName, span)
{
    var td, tr, autoRow;
    //console.log("tableName: "+tableName);
    tr = document.createElement("tr");
    //tr.setAttribute("width", "100%");
    autoRow = document.getElementById(tableName);
    //console.log(autoRow.childNodes);
    td = document.createElement("td");
    td.setAttribute("colspan", span);
    tr.appendChild(td);
    autoRow.appendChild(tr);

    return autoRow;
}

function clearTable(tableName) {
    var table_to_clean;

    table_to_clean = document.getElementById(tableName);
    //console.log(tableName+" Length: "+table_to_clean.childNodes.length);
    if (table_to_clean.childNodes.length > 0) {
        for (loop = table_to_clean.childNodes.length -1; loop >= 0 ; loop--) {
            if(loop < 4)
            {
                continue;
            }
            table_to_clean.removeChild(table_to_clean.childNodes[loop]);
        }
    }
}

function myLoop () {           //  create a loop function
    setInterval(function () {    //  call a 3s setTimeout when the loop is called
        send('import_waiting');          //  your code here
        curentRequest = "import_waiting";
    }, 2000);
}