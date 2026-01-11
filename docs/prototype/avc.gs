
//Note: gnum and mnum are based on row number not the actual group or member number respectively.
//This system will not send txts just emails. There is no need to include phone numbers. This will help with security.

var debug = false; //Either any errors will turn up on the sheet (debug) or be logged and an email sent (debug = false)


//Member sheet constants
var mnumcol = 1; //Members sheet Member number column
var groupcol = 2; //Group number
var namecol = 3; //first name
var lnamecol = 4; //last name
var cnamecol = 5; //code name
var phonecol = 6; //phone number
var emailcol = 7; //email. 
var mdidcol = 9; //Member dashboard ID column
var mdurlcol = 10; //Member dashboard URl column.
var errorcol = 14; // put an error in this column.

//Group sheets
var grnumcol = 1; //Group Number.
var grnamecol = 2; // Group Name column. This is what indicates the group exists.
var gridcol = 3; //Group Doc ID

// Assets sheet
var anumcol = 1; // asset number
var atcol = 2; // asset type, Project, Doc, Resource
var anamecol = 3; // Asset Name column
var aURLcol = 4; // Asset URL Column
var aIDcol = 5; // Column where the DocID is.
var aicol = 6; // Asset initiator column
var agcol = 7; // Asset Gatekeeper column
var aacol = 8; // Asset Approver column
var adcol = 9; // Asset Destination column
var apcol = 10; // How is the Process going column


//Asset workflow table
var awfwcol = 0; //Document workflow workflow type column
var awftcol = 1; //Document workflow Type column
var awfncol = 2; //Document workflow number column
var awfnamecol = 3; //Document workflow name column
var awfccol = 4; //Document workflow comment column
var awflcol = 5; //Document workflow log column

//Destinations sheet
var dnumcol = 1; //Destination sheet destination number column
var dnamecol = 2; //Destination folder name column
var didcol = 3; //Destination folder ID column

//Member-Group sheet
var mgmemcol = 1; //This is the member number
var mggrocol = 2; //This is the group number
var mgnotcol = 3; //This is the notification preference for that group member

// Colours
var goodcolor = "#CCFFCC"; // Light Green
var errorcolor = "#FFCCCC"; // Light Red
var fixedcolor = "#CCFF99"; // not as light Green
var missingcolor = "#ffbf00"; // amber

//Todo

// function onOpen(e) {
//   var ui = DocumentApp.getUi();

//   ui.createMenu('AVC Functions')
//     .addItem('1. How to and Help', 'showHelp')
//     .addItem('2. Add this Doc', 'addDoc')
//     .addItem('3. Check Doc', 'checkDoc')
//     .addItem('4. Process Doc', 'processDoc')
//     .addItem('5. Send again', 'sendagain')
//     .addSeparator()
//     .addSubMenu(
//       ui.createMenu('Advanced')
//         .addItem('New Submenu', 'getRangeValues')
//     )
//     .addToUi();
// }

function onOpen() {
  // Since all the code in AVCommonsApp can be called from docs, eg project docs, we need to store the 
  // sheet's ID so it can be called when needed. This is part of ensuring our code is centralized.
  // There might be a better way to do this, but this will work and saves us having to hardcode the ID
  // somewhere.
  // var scriptProperties = PropertiesService.getScriptProperties();
  // scriptProperties.setProperty('ssID', SpreadsheetApp.getActiveSpreadsheet().getId());
  PropertiesService.getScriptProperties().setProperty('ssID', SpreadsheetApp.getActiveSpreadsheet().getId());

  SpreadsheetApp.getUi().createAddonMenu()
    .addItem('Start', 'showSidebar')
    .addToUi();
  var menuEntries = [
    { name: "1. How to and Help", functionName: "showAVCHelp" },
    { name: "2. Check Doc", functionName: "checkAsset" },
    { name: "3. Check Docs", functionName: "checkDocs" },
    { name: "4. Process Doc", functionName: "processDoc" },
    { name: "5. Process Docs", functionName: "processDocs" },
    { name: "6. Create Member Dashboards", functionName: "createMemberDocs" },
    { name: "7. Update Member Dashboards", functionName: "updateMemberDocs" },
    { name: "8. Create Group Dashboards", functionName: "createGroupDashboards" },
    { name: "9. Update Group Dashboards", functionName: "updateGroupDashboards" },
    { name: "10. Update Member-Groups", functionName: "updateMemberGroups" },
    { name: "11. Duplicate AVCommonsApp", functionName: "copyAVC" },
  ];
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  ss.addMenu("AVC Functions", menuEntries);
}


function onProjectOpen(e) {
  var ui = DocumentApp.getUi();

  ui.createMenu('AVC Functions')
    .addItem('1. How to and Help', 'showProjectHelp')
    .addItem('2. Add this Doc', 'addDoc')
    .addItem('3. Check Doc', 'checkDoc')
    .addItem('4. Process Doc', 'processDoc')
    .addItem('5. Send again', 'sendagain')
    .addSeparator()
    .addSubMenu(
      ui.createMenu('Advanced')
        .addItem('New Submenu', 'getRangeValues')
    )
    .addToUi();
}

function onResourceOpen(e) {
  var ui = DocumentApp.getUi();

  ui.createMenu('AVC Functions')
    .addItem('1. How to and Help', 'showResourceHelp')
    .addItem('2. Add this Doc', 'addDoc')
    .addItem('3. Check Doc', 'checkDoc')
    .addItem('4. Process Doc', 'processDoc')
    .addItem('5. Send again', 'sendagain')
    .addSeparator()
    .addSubMenu(
      ui.createMenu('Advanced')
        .addItem('New Submenu', 'getRangeValues')
    )
    .addToUi();
}

function onDocumentOpen(e) {
  var ui = DocumentApp.getUi();

  ui.createMenu('AVC Functions')
    .addItem('1. How to and Help', 'showDocumentHelp')
    .addItem('2. Add this Doc', 'addDoc')
    .addItem('3. Check Doc', 'checkDoc')
    .addItem('4. Process Doc', 'processDoc')
    .addItem('5. Send again', 'sendagain')
    .addSeparator()
    .addSubMenu(
      ui.createMenu('Advanced')
        .addItem('New Submenu', 'getRangeValues')
    )
    .addToUi();
}

// Menu for member dashboard docs.
function onMemberDashboardOpen(e) {
  var ui = DocumentApp.getUi();

  ui.createMenu('AVC Functions')
    .addItem('1. How to and Help', 'showMemberDashboardHelp')
    .addItem('2. Update this doc', 'updateMDDoc')
    .addItem('2. Update my preferences', 'updateMemberPreferences')

    .addToUi();
}

// Menu for group dashboard docs.
function onGroupDashboardOpen(e) {
  var ui = DocumentApp.getUi();

  ui.createMenu('AVC Functions')
    .addItem('1. How to and Help', 'showGroupDashboardHelp')
    .addItem('2. Update this doc', 'updateGRDashboard')
    .addToUi();
}

function showAVCHelp() {
  // Show help for the AV Commons app sheet
  var html = HtmlService.createHtmlOutput('<!DOCTYPE html>\
<html>\
  <head>\
    <base target="_top">\
  </head>\
  <body>\
<p><iframe allowfullscreen="" src="https://docs.google.com/document/d/1N-6p9qcleW9NsTTPWTpQtM4Nz28fD9mFt-clRmsuaeI/edit?usp=sharing" \
    style="width:100%;position: absolute; height: 100%; border: none;"></iframe></p>\
  </body>\
</html>')
    .setWidth(1000)
    .setHeight(1000)
    .setSandboxMode(HtmlService.SandboxMode.IFRAME);
  SpreadsheetApp.getUi().showModalDialog(html, 'Help');
}

function showMemberDashboardHelp() {
  var html = HtmlService.createHtmlOutput('<!DOCTYPE html>\
<html>\
  <head>\
    <base target="_top">\
  </head>\
  <body>\
    <p><iframe allowfullscreen="" src="https://docs.google.com/document/d/1qGi4NqToKxS2cEKeX5Q1KzkST0O_FRVKt1_DGoxyZqY/edit?usp=sharing" \
    style="width:100%;position: absolute; height: 100%; border: none;"></iframe></p>\
  </body>\
</html>')
    .setWidth(1000)
    .setHeight(1000)
    .setSandboxMode(HtmlService.SandboxMode.IFRAME);
  DocumentApp.getUi().showModalDialog(html, 'Help');

}

function showGroupDashboardHelp() {
  var html = HtmlService.createHtmlOutput('<!DOCTYPE html>\
<html>\
  <head>\
    <base target="_top">\
  </head>\
  <body>\
    <p><iframe allowfullscreen="" src="https://docs.google.com/document/d/1qGi4NqToKxS2cEKeX5Q1KzkST0O_FRVKt1_DGoxyZqY/edit?usp=sharing" \
    style="width:100%;position: absolute; height: 100%; border: none;"></iframe></p>\
  </body>\
</html>')
    .setWidth(1000)
    .setHeight(1000)
    .setSandboxMode(HtmlService.SandboxMode.IFRAME);
  DocumentApp.getUi().showModalDialog(html, 'Help');

}

function showProjectHelp() {
  var html = HtmlService.createHtmlOutput('<!DOCTYPE html>\
<html>\
  <head>\
    <base target="_top">\
  </head>\
  <body>\
    <p><iframe allowfullscreen="" src="https://docs.google.com/document/d/1jRejpKY5E_aEpzyk5MbEUkzJzi2_MOoiji47M8xJ_Jw/edit?usp=sharing" \
    style="width:100%;position: absolute; height: 100%; border: none;"></iframe></p>\
  </body>\
</html>')
    .setWidth(1000)
    .setHeight(1000)
    .setSandboxMode(HtmlService.SandboxMode.IFRAME);
  DocumentApp.getUi().showModalDialog(html, 'Help');

}

function showResourceHelp() {
  var html = HtmlService.createHtmlOutput('<!DOCTYPE html>\
<html>\
  <head>\
    <base target="_top">\
  </head>\
  <body>\
    <p><iframe allowfullscreen="" src="https://docs.google.com/document/d/1jiE69ZGG0h3qnVCqmPVP6ndCdPnPJ6w1QBXqKWhJgJ0/edit?usp=sharing" \
    style="width:100%;position: absolute; height: 100%; border: none;"></iframe></p>\
  </body>\
</html>')
    .setWidth(1000)
    .setHeight(1000)
    .setSandboxMode(HtmlService.SandboxMode.IFRAME);
  DocumentApp.getUi().showModalDialog(html, 'Help');

}

function showDocumentHelp() {
  var html = HtmlService.createHtmlOutput('<!DOCTYPE html>\
<html>\
  <head>\
    <base target="_top">\
  </head>\
  <body>\
    <p><iframe allowfullscreen="" src="https://docs.google.com/document/d/1HFGnG844A23o4S-7t1LuTQvorroVzzNBbgXRS2CQrGQ/edit?usp=sharing" \
    style="width:100%;position: absolute; height: 100%; border: none;"></iframe></p>\
  </body>\
</html>')
    .setWidth(1000)
    .setHeight(1000)
    .setSandboxMode(HtmlService.SandboxMode.IFRAME);
  DocumentApp.getUi().showModalDialog(html, 'Help');

}

function checkAsset() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  try {
    var dsheet = ss.getSheetByName("Assets");
    var sheet = ss.getActiveSheet();
    var selection = ss.getSelection();
    var srange = selection.getCurrentCell();
    if (srange.getNumRows() == 1 && srange.getNumColumns() == 1 && sheet.getName() == "Assets") {
      //  var scol = srange.getLastColumn();
      var srow = srange.getLastRow();
      checkDoc(srange.getValue().toString());

    }

  } catch (e) {
    dsheet.getRange(1, errorcol, 1, 1).setValue(e.message + " : " + e.lineNumber);
    HWcolor = "purple";
    dsheet.getRange(1, errorcol, 1, 1).setBackground(HWcolor);

  }

}


function processDoc() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  try {
    var dsheet = ss.getSheetByName("Assets");
    var sheet = ss.getActiveSheet();
    var selection = ss.getSelection();
    var srange = selection.getCurrentCell();
    var tnumrows = srange.getNumRows();
    var tnumcols = srange.getNumColumns();
    var tsheetname = sheet.getName();
    if (srange.getNumRows() == 1 && srange.getNumColumns() == 1 && sheet.getName() == "Assets") {
      //  var scol = srange.getLastColumn();
      var srow = srange.getLastRow();

      // Check the doc first
      var docID = srange.getValue().toString()
      var cflag = checkaDoc(docID);
      if (cflag) {
        dsheet.getRange(srow, apcol, 1, 1).setValue("Problem with checking the doc");
      } else {
        var pflag = processaDoc(docID);
        if (cflag) {
          dsheet.getRange(srow, apcol, 1, 1).setValue("Problem with processing the doc");
        } else {
          //
          dsheet.getRange(srow, apcol, 1, 1).setValue("Doc checked and processed");
        }
      }
    }
  } catch (e) {
    dsheet.getRange(1, errorcol, 1, 1).setValue(e.message + " : " + e.lineNumber);
    HWcolor = "purple";
    dsheet.getRange(1, errorcol, 1, 1).setBackground(HWcolor);

  }
}

function processDocs() {
  var ssID = PropertiesService.getScriptProperties().getProperty('ssID');
  var ss = SpreadsheetApp.openById(ssID);
  try {
    var dsheet = ss.getSheetByName("Assets");

    //finish this function.
    var dnum = 1; //Document Number

    while (dsheet.getRange(dnum + 1, docIDcol, 1, 1).getValues().toString() != "") {

      // Check the doc first
      var docID = dsheet.getRange(dnum + 1, docIDcol, 1, 1).getValues().toString();
      var cflag = checkaDoc(docID);
      if (cflag) {
        dsheet.getRange(srow, apcol, 1, 1).setValue("Problem with checking the doc");
      } else {
        var pflag = processaDoc(docID);
        if (cflag) {
          dsheet.getRange(srow, apcol, 1, 1).setValue("Problem with processing the doc");
        } else {
          //
          dsheet.getRange(srow, apcol, 1, 1).setValue("Doc checked and processed");
        }


      }
    }
  } catch (e) {
    dsheet.getRange(1, errorcol, 1, 1).setValue(e.message + " : " + e.lineNumber);
    HWcolor = "purple";
    dsheet.getRange(1, errorcol, 1, 1).setBackground(HWcolor);

  }
}


function updateMDDoc(docID, mnum) {
  // set up docID for testing.
  if (docID == null) {
    docID = "1myAsK6VLjvXP_RTXkKOUEbMiB8nqfY9Hjy-zA1R76UE";
    //mnum = 9;//this is the row number, 
  }
  try {
    var ssID = PropertiesService.getScriptProperties().getProperty('ssID');
    var ss = SpreadsheetApp.openById(ssID);
    var msheet = ss.getSheetByName("Members");
    if (mnum == null || mnum == undefined) {
      var mcount = 2; //This is used to work out the member number
      var fflag = false;
      while (msheet.getRange(mcount, mdidcol, 1, 1).getValues().toString() !== "") {
        if (docID == msheet.getRange(mcount, mdidcol, 1, 1).getValues().toString()) {
          //Found it
          mnum = mcount;
          fflag = true;
          break;
        }
        mcount++;
      }
      if (!fflag) {
        return "Did not find the member number. Please report this to rjzaar@gmail.com";
      }

      //  if (mnum==null) {
      //   //Will need to work out the member number from the member's table
      //   return "Did not find the member number. Please report this to rjzaar@gmail.com";
      //  }
    }

    var pdoc = DocumentApp.openById(docID);

    var asheet = ss.getSheetByName("Assets");
    // Remove everything after the line
    var body = pdoc.getBody();
    //This can be deleted once it has been run.
    //Give the dashboard the right title
    var mname = msheet.getRange(mnum, namecol, 1, 1).getValues().toString() + " " + msheet.getRange(mnum, lnamecol, 1, 1).getValues().toString();
    body.replaceText('AV Commons Member', mname);



    var numElem = body.getNumChildren();
    for (var i = numElem - 2; i > 1; i--) {
      // Working backwards find the last horizontal rule
      var etype = body.getChild(i).asText().getText();
      if (body.getChild(i).asText().getText() == "Everything below this line is created by the system. Any edits will be lost.") {
        break;
      }
      body.getChild(i).removeFromParent();
    }
    body.appendHorizontalRule();
    body.appendParagraph("");

    //Now add the Member table
    var table = body.appendTable();
    var tr = table.appendTableRow();

    var td = tr.appendTableCell('Member');
    var mname = msheet.getRange(mnum, namecol, 1, 1).getValues().toString() + " " + msheet.getRange(mnum, lnamecol, 1, 1).getValues().toString();
    var td = tr.appendTableCell(mname);
    var tr = table.appendTableRow();
    var td = tr.appendTableCell('Notifications');

    var td = tr.appendTableCell('n');
    var tr = table.appendTableRow();
    var td = tr.appendTableCell('Last Updated');
    var dateString = Utilities.formatDate(new Date(), "GMT+10", "dd/MM:HH:mm");
    var td = tr.appendTableCell(dateString);
    table.setColumnWidth(0, 80);
    table.setColumnWidth(1, 350);
    body.appendParagraph("Personal ");
    let link = body.appendParagraph("Notifications").setLinkUrl("https://docs.google.com/document/d/1gQDEz3qtUyjBK2_QO4JTakf61bITMoj1tfDo4P-062c/edit#bookmark=id.tkl12mwho9ri");
    link.merge();
    let closing = body.appendParagraph(" ( n:new, d:daily, w:weekly, x:not at all)");
    closing.merge();

    body.appendParagraph("");

    //Now add the Group table
    var table = body.appendTable();
    var tr = table.appendTableRow();

    var td = tr.appendTableCell('Groups');
    var td = tr.appendTableCell('Member');
    var td = tr.appendTableCell('Notification');

    //go through and every group there is
    var gsheet = ss.getSheetByName("Groups");
    var gnum = 2;
    while (gsheet.getRange(gnum, 2, 1, 1).getValues().toString() !== "") {

      var tr = table.appendTableRow();
      var gtext = gsheet.getRange(gnum, 2, 1, 1).getValues().toString();
      var td = tr.appendTableCell(gtext); // Add Group name. 
      if (gsheet.getRange(gnum, 6, 1, 1).getValues().toString() == "y") { // If public group add link.
        var alink = td.editAsText();
        var grlink = "https://docs.google.com/document/d/" + gsheet.getRange(gnum, 3, 1, 1).getValues().toString() + "/edit";


        if (gsheet.getRange(gnum, 5, 1, 1).getValues().toString() !== "") { // If Group has AV website Group, add link
          alink.appendText(" (AV Website Group)");

          alink.setLinkUrl(gtext.length + 2, gtext.length + 17, gsheet.getRange(gnum, 5, 1, 1).getValues().toString())
          // todo
          // let glink = body.appendParagraph("Notifications").setLinkUrl("https://docs.google.com/document/d/1gQDEz3qtUyjBK2_QO4JTakf61bITMoj1tfDo4P-062c/edit#bookmark=id.tkl12mwho9ri");
          //  glink.merge();
        }
        alink.setLinkUrl(0, gtext.length - 1, grlink);
      }
      var td = tr.appendTableCell("");
      var td = tr.appendTableCell("");


      gnum++;
    }


    //Now indicate if a member of each group
    var mgsheet = ss.getSheetByName("Member-Groups");

    var mgnum = 2;
    var smgnum = 0;

    while (mgsheet.getRange(mgnum, 1, 1, 1).getValues().toString() !== "") {
      var smgnum = +mgsheet.getRange(mgnum, 2, 1, 1).getValues().toString();
      if (mgsheet.getRange(mgnum, 1, 1, 1).getValues().toString() == mnum - 1) {
        //indicate the user is part of this group.
        table.getCell(smgnum, 1).editAsText().setText("y");
        table.getCell(smgnum, 2).editAsText().setText(mgsheet.getRange(mgnum, 6, 1, 1).getValues().toString());
        table.getCell(smgnum, 0).setBackgroundColor("#7FFF00");
        table.getCell(smgnum, 1).setBackgroundColor("#7FFF00");
        table.getCell(smgnum, 2).setBackgroundColor("#7FFF00");
        //also add the links if it is a private group since they are a member of it.
        if (gsheet.getRange(smgnum + 1, 6, 1, 1).getValues().toString() !== "y") { // If public group add link.
          var alink = table.getCell(smgnum, 0).editAsText();
          var grlink = "https://docs.google.com/document/d/" + gsheet.getRange(smgnum + 1, 3, 1, 1).getValues().toString() + "/edit";


          if (gsheet.getRange(smgnum + 1, 5, 1, 1).getValues().toString() !== "") { // If Group has AV website Group, add link
            alink.appendText(" (AV Website Group)");

            alink.setLinkUrl(gtext.length + 2, gtext.length + 17, gsheet.getRange(smgnum + 1, 5, 1, 1).getValues().toString())
          }
          alink.setLinkUrl(0, gtext.length - 1, grlink);
        }
      }
      mgnum++;
    }

    table.setColumnWidth(0, 220);
    table.setColumnWidth(1, 80);
    table.setColumnWidth(2, 80);
    if (smgnum == 0) {
      body.appendParagraph("You are not in any groups.");

    }

    body.appendParagraph("Group ");
    let glink = body.appendParagraph("Notifications").setLinkUrl("https://docs.google.com/document/d/1gQDEz3qtUyjBK2_QO4JTakf61bITMoj1tfDo4P-062c/edit#bookmark=id.tkl12mwho9ri");
    glink.merge();
    let gclosing = body.appendParagraph(" ( p:personal, n:new, d:daily, w:weekly, x:not at all)");
    gclosing.merge();


    body.appendParagraph("");
    body.appendParagraph("ASSETS");
    body.appendParagraph("");

    var nassets = 0;



    // Now go through all the docs and see if this particular user is listed in any of them.
    var anum = 2; //Asset Number
    while (asheet.getRange(anum, aIDcol, 1, 1).getValues().toString() !== "") {
      var aID = asheet.getRange(anum, aIDcol, 1, 1).getValues().toString();
      var adoc = DocumentApp.openById(aID);
      var atables = adoc.getBody().getTables();
      var numatables = atables.length;
      var atable = atables[numatables - 1];
      var anumrows = atable.getNumRows();
      var astage = 1;
      var nstages = 0;
      // a is for checking through all the stages for this particular document.
      for (var a = 1; a < anumrows - 1; a++) {
        // Check to see if the stage pointer needs to be moved, by checking the comment column
        if (atable.getCell(a, awfccol).getText() !== "") {
          //Move pointer
          astage++;
        }


        if (atable.getCell(a, awfnamecol).getText() == mname) {
          //Found a match so add it to the table
          nassets++;
          if (nassets == 1) {
            //Create the Assets Table
            var table = body.appendTable();
            var tr = table.appendTableRow();

            var td = tr.appendTableCell('ID');
            var td = tr.appendTableCell('Name (and link)');
            var td = tr.appendTableCell('Type');
            var td = tr.appendTableCell('Stage');
            var td = tr.appendTableCell('My Role');
          }
          // Now add the row
          nstages++;
          var tr = table.appendTableRow();

          var td = tr.appendTableCell(asheet.getRange(anum, anumcol, 1.1).getValues().toString());
          var td = tr.appendTableCell(asheet.getRange(anum, anamecol, 1.1).getValues().toString());
          var alink = td.editAsText();
          alink.setLinkUrl(asheet.getRange(anum, aURLcol, 1.1).getValues().toString());
          var td = tr.appendTableCell(asheet.getRange(anum, atcol, 1.1).getValues().toString());

          // To work out what stage the document is at we need to keep track of anything in the Comment column.

          var td = tr.appendTableCell('Stage');
          var td = tr.appendTableCell(atable.getCell(a, 0).getText());


        }
      }
      // Now add in the number of times the current stage is needed.
      while (nstages > 0) {
        var snnumrows = table.getNumRows();
        table.getCell(snnumrows - nstages, 3).setText(atable.getCell(astage, 0).getText());
        if (table.getCell(snnumrows - nstages, 3).getText() == table.getCell(snnumrows - nstages, 4).getText()) {
          table.getCell(snnumrows - nstages, 1).setBackgroundColor(goodcolor);
        }


        nstages--;
      }

      if (nassets > 0) {
        table.setColumnWidth(0, 30);
        table.setColumnWidth(1, 190);
        table.setColumnWidth(2, 50);
        table.setColumnWidth(3, 100);
        table.setColumnWidth(4, 100);
      }

      adoc.saveAndClose();
      anum++;
    }

    if (nassets == 0) {
      //Not mentioned in any assets so give that message
      body.appendParagraph("Yay! No projects to work on.");
    }
    body.appendParagraph("");

    //Now add any Group Docs the member is a member of. Do it via each group.
    var mgsheet = ss.getSheetByName("Member-Groups");
    var gsheet = ss.getSheetByName("Groups");

    var mgnum = 1;

    while (mgsheet.getRange(mgnum, 1, 1, 1).getValues().toString() !== "") {
      var smgnum = +mgsheet.getRange(mgnum, 2, 1, 1).getValues().toString() + 1;
      if (mgsheet.getRange(mgnum, 1, 1, 1).getValues().toString() == mnum - 1) {

        var grname = gsheet.getRange(smgnum, grnamecol, 1, 1).getValues().toString();
        addGroupTable(ss, body, grname);

      }
      mgnum++;
    }



    //Save and close the document
    pdoc.saveAndClose();

  } catch (e) {
    logTechIssue("updateMDDoc Error", e.stack, mnum);
  }

}

function addGroupTable(ss, body, grname) {
  //This function will add a Group table for that member.
  try {
    body.appendParagraph(grname);


    var asheet = ss.getSheetByName("Assets");
    var nassets = 0;



    // Now go through all the docs and see if this particular group is listed in any of them.
    var anum = 2; //Asset Number
    while (asheet.getRange(anum, aIDcol, 1, 1).getValues().toString() !== "") {
      var aID = asheet.getRange(anum, aIDcol, 1, 1).getValues().toString();
      var adoc = DocumentApp.openById(aID);
      var atables = adoc.getBody().getTables();
      var numatables = atables.length;
      var atable = atables[numatables - 1];
      var anumrows = atable.getNumRows();
      var astage = 1;
      var nstages = 0;
      // a is for checking through all the stages for this particular document.
      for (var a = 1; a < anumrows - 1; a++) {
        // Check to see if the stage pointer needs to be moved, by checking the comment column
        if (atable.getCell(a, awfccol).getText() !== "") {
          //Move pointer
          astage++;
        }


        if (atable.getCell(a, awfnamecol).getText() == grname) {
          //Found a match so add it to the table
          nassets++;
          if (nassets == 1) {
            //Create the Assets Table
            var table = body.appendTable();
            var tr = table.appendTableRow();

            var td = tr.appendTableCell('ID');
            var td = tr.appendTableCell('Name (and link)');
            var td = tr.appendTableCell('Type');
            var td = tr.appendTableCell('Stage');
            var td = tr.appendTableCell('Group Role');
            var td = tr.appendTableCell('Assigned to');
          }
          // Now add the row
          nstages++;
          var tr = table.appendTableRow();

          var td = tr.appendTableCell(asheet.getRange(anum, anumcol, 1.1).getValues().toString());
          var td = tr.appendTableCell(asheet.getRange(anum, anamecol, 1.1).getValues().toString());
          var alink = td.editAsText();
          alink.setLinkUrl(asheet.getRange(anum, aURLcol, 1.1).getValues().toString());
          var td = tr.appendTableCell(asheet.getRange(anum, atcol, 1.1).getValues().toString());

          // To work out what stage the document is at we need to keep track of anything in the Comment column.

          var td = tr.appendTableCell('Stage');
          var td = tr.appendTableCell(atable.getCell(a, 0).getText());
          var td = tr.appendTableCell();


        }
      }
      // Now add in the number of times the current stage is needed.
      while (nstages > 0) {
        var snnumrows = table.getNumRows();
        table.getCell(snnumrows - nstages, 3).setText(atable.getCell(astage, 0).getText());
        if (table.getCell(snnumrows - nstages, 3).getText() == table.getCell(snnumrows - nstages, 4).getText()) {
          table.getCell(snnumrows - nstages, 1).setBackgroundColor(goodcolor);
        }


        nstages--;
      }

      if (nassets > 0) {
        table.setColumnWidth(0, 30);
        table.setColumnWidth(1, 180);
        table.setColumnWidth(2, 50);
        table.setColumnWidth(3, 80);
        table.setColumnWidth(4, 80);
        table.setColumnWidth(5, 60);
      }

      adoc.saveAndClose();
      anum++;
    }

    if (nassets == 0) {
      //Not mentioned in any assets so give that message
      body.appendParagraph("Yay! No projects to work on.");
    }
    body.appendParagraph("");
  } catch (e) {
    DocumentApp.getUi().alert(e.message + " : " + e.lineNumber);

  }
}



function getMemberGroups(mnum) {
  try {
    var ssID = PropertiesService.getScriptProperties().getProperty('ssID');
    var ss = SpreadsheetApp.openById(ssID);
    var mgsheet = ss.getSheetByName("Member-Groups");
    var gsheet = ss.getSheetByName("Groups");
    var groups = "";
    var mgnum = 1;
    var smgnum = 0;
    var ngroups = 0; //Number of Groups
    while (mgsheet.getRange(mgnum, 1, 1, 1).getValues().toString() !== "") {
      var smgnum = +mgsheet.getRange(mgnum, 2, 1, 1).getValues().toString();
      if (mgsheet.getRange(mgnum, 1, 1, 1).getValues().toString() == mnum - 1) {
        //Add the group to group list
        ngroups++;
        if (ngroups == 1) {
          groups = gsheet.getRange(smgnum + 1, 2, 1, 1).getValues().toString();
        } else {
          groups = groups + ", " + gsheet.getRange(smgnum + 1, 2, 1, 1).getValues().toString();
        }

      }
      mgnum++;
    }
    return groups;
  } catch (e) {
    DocumentApp.getUi().alert(e.message + " : " + e.lineNumber);

  }
}

function getMemberGroupLinks(mnum) {

  try {
    if (mnum == null || mnum == undefined) {
      mnum = 7;
    }
    var ssID = PropertiesService.getScriptProperties().getProperty('ssID');
    var ss = SpreadsheetApp.openById(ssID);
    var mgsheet = ss.getSheetByName("Member-Groups");
    var gsheet = ss.getSheetByName("Groups");
    var groups = "";
    var mgnum = 1;
    var smgnum = 0;
    var ngroups = 0; //Number of Groups
    while (mgsheet.getRange(mgnum, 1, 1, 1).getValues().toString() !== "") {
      var smgnum = +mgsheet.getRange(mgnum, 2, 1, 1).getValues().toString();
      if (mgsheet.getRange(mgnum, 1, 1, 1).getValues().toString() == mnum - 1) {
        //Add the group to group list
        ngroups++;
        if (ngroups == 1) {
          groups = '<a href="' + gsheet.getRange(smgnum + 1, 4, 1, 1).getValues().toString() + '">' + gsheet.getRange(smgnum + 1, 2, 1, 1).getValues().toString() + '</a>';
        } else {
          groups = groups + ", " + '<a href="' + gsheet.getRange(smgnum + 1, 4, 1, 1).getValues().toString() + '">' + gsheet.getRange(smgnum + 1, 2, 1, 1).getValues().toString() + '</a>';
        }

      }
      mgnum++;
    }
    return groups;
  } catch (e) {
    DocumentApp.getUi().alert(e.message + " : " + e.lineNumber);

  }
}

function createMemberDocs() {
  // This function will add any member docs that are missing from the Member's sheet.
  var ssID = PropertiesService.getScriptProperties().getProperty('ssID');
  var ss = SpreadsheetApp.openById(ssID);
  var msheet = ss.getSheetByName("Members");
  var mnum = 1;
  while (msheet.getRange(mnum, namecol, 1, 1).getValues().toString() !== "") {
    if (msheet.getRange(mnum, mdidcol, 1, 1).getValues().toString() == "") {
      createMemberDoc(msheet.getRange(mnum, emailcol, 1, 1).getValues().toString());
    }
    mnum++;
  }
}

function updateMemberDocs() {
  // This function will update all member docs in the Member's sheet.
  var ssID = PropertiesService.getScriptProperties().getProperty('ssID');
  var ss = SpreadsheetApp.openById(ssID);
  var msheet = ss.getSheetByName("Members");
  var mnum = 2;
  while (msheet.getRange(mnum, namecol, 1, 1).getValues().toString() !== "") {
    if (msheet.getRange(mnum, mdidcol, 1, 1).getValues().toString() !== "") {
      updateMDDoc(msheet.getRange(mnum, mdidcol, 1, 1).getValues().toString(), mnum);
    }
    mnum++;
  }
}

function createMemberDoc(memail) {
  //This function will create a member doc based on the member's email. It is presumed there is an entry in the Members table
  // with all the member's details.
  var ssID = PropertiesService.getScriptProperties().getProperty('ssID');
  var ss = SpreadsheetApp.openById(ssID);
  var msheet = ss.getSheetByName("Members");
  var mnum = 1;
  var fflag = false;
  while (msheet.getRange(mnum, mnumcol, 1, 1).getValues().toString() !== "") {
    if (msheet.getRange(mnum, emailcol, 1, 1).getValues().toString() == memail) {
      // Found it!
      fflag = true;
      break;
    }
    mnum++;
  }
  if (fflag == false) {
    DocumentApp.getUi().alert("Could not find the member name matching the email " + uemail);
    return 1;
  }
  var mname = msheet.getRange(mnum, namecol, 1, 1).getValues().toString() + " " + msheet.getRange(mnum, lnamecol, 1, 1).getValues().toString();
  //todo: the member dashboard template could be found from the assests table. But for now it's hardcoded.
  var matID = "1tJGfmvre-mhhFUW3RByK5NN9YTaMfMPS1cQ7Drsi_fs";
  var file = DriveApp.getFileById(matID);

  var folder = DriveApp.getFolderById("1qCfjOJuEJmccPYt7s5gBebAP2kfANTWA");
  var mdID = file.makeCopy(mname + " Dashboard", folder).getId();
  var mddoc = DocumentApp.openById(mdID);
  //Store the ID in the members sheet. Everything will work off that ID
  msheet.getRange(mnum, mdidcol, 1, 1).setValue(mdID);
  mddoc.saveAndClose();
  updateMDDoc(mdID, mnum);

}

function addDoc(docID) {
  // set up docID for testing.
  if (docID == null) {
    docID = "1_KtLYY1NbrgyhSa3v0T4E8JlZ3hhtPLqvrACh_2TJEI";
  }
  try {
    var pdoc = DocumentApp.openById(docID);

    //This function adds the doc into the AV Commons App, ie adds it into the AV Commons Add Google Sheet.
    var ssID = PropertiesService.getScriptProperties().getProperty('ssID');
    var ss = SpreadsheetApp.openById(ssID);
    var eflag = false;

    var asheet = ss.getSheetByName("Assets");
    var anum = 1;
    var aname = asheet.getRange(anum, anamecol, 1, 1).getValues().toString();
    while (aname !== "") {
      anum++;
      aname = asheet.getRange(anum, anamecol, 1, 1).getValues().toString();
    }

    // Add in the asset name
    aname = pdoc.getName();
    //check if there is an asset with that name already
    var nflag = checkname(aname);
    if (nflag) {
      return 1;
    }

    // Work out what type of doc this is
    var atype = getType(docID);

    // Add in the initiator
    var editors = pdoc.getEditors();
    var numeditors = editors.length;
    var aemail = "";
    if (numeditors == 1) {
      aemail = editors[0].getEmail();

    } else if (numeditors == 2) {
      if (editors[0].getEmail() == "au@myavila.com") {
        aemail = editors[0].getEmail();
      } else {
        aemail = editors[1].getEmail();
      }
    } else {
      // We'll need to ask - this is a todo task! for now get them to do it.
      DocumentApp.getUi().alert("There are too many editors of this document. Please set it up so only you are the editor and try again.");
      return 1;
    }
    //Now replace the email with their name.
    var mname = getOwner(aemail);
    if (mname == 1) {
      // There was an error.
      eflag = true;
    }

    if (eflag == false) {
      // All good add values
      // Add in the asset number
      //if (asheet.getRange(anum,anumcol,1,1).getValues.toString()==""){
      asheet.getRange(anum, anumcol, 1, 1).setValue(anum - 1);
      //}
      // Add in asset type
      asheet.getRange(anum, atcol, 1, 1).setValue(atype);
      // Add in asset name
      asheet.getRange(anum, anamecol, 1, 1).setValue(aname);
      // Add in the URl Col
      asheet.getRange(anum, aURLcol, 1, 1).setValue('="https://docs.google.com/document/d/"&E' + anum + '&"/edit"');
      // Add in the DocID
      asheet.getRange(anum, aIDcol, 1, 1).setValue(docID);
      // Add in the initiator name
      asheet.getRange(anum, aicol, 1, 1).setValue(mname);
      // Other names will be added to gatekeeper as the document is processed.

      // Add the Name of the person into the Workflow table

      var ptables = pdoc.getBody().getTables();
      var numtables = ptables.length;
      var ptable = ptables[numtables - 1];
      ptable.getCell(1, awfnamecol).setText(mname);

      DocumentApp.getUi().alert("This document has been added to AV Commons ready to be checked and processed.");
    }
    pdoc.saveAndClose();
  } catch (e) {
    DocumentApp.getUi().alert("Add Doc error: " + e.message + " : " + e.lineNumber);

  }
}

function getType(docID) {
  // Work out what type of doc this is
  try {
    var pdoc = DocumentApp.openById(docID);


    // First check that there is a workflow table at the end of the doc
    var ptables = pdoc.getBody().getTables();
    var numtables = ptables.length;
    var ptable = ptables[numtables - 1];

    if (ptable.getCell(0, 0).getText() == "Workflow") {
      // yes it has a ptable
      // Projects have a table with 'ID' in the first cell
      // Go through all the tables and check to see if one of the other tables has a first cell with 'ID' or 'Resource' in it.
      var idflag = false;
      var rflag = false;
      for (var tnum = 0; tnum < numtables - 1; tnum++) {
        if (ptables[tnum].getCell(0, 0).getText() == "ID") {
          idflag = true;
        }
        if (ptables[tnum].getCell(0, 0).getText() == "Resource") {
          rflag = true;
        }
      }
      var atype = "";
      if (idflag) {
        atype = "Project";
      } else if (rflag) {
        atype = "Resource";
      } else {
        atype = "Doc";
      }
    } else {
      //no ptable! give a message and end
      DocumentApp.getUi().alert("getType error: There is no workflow table at the end of this document. You will need to start from a fresh template or add the workflow table if you wish to add this asset to the AV Commons.");

      return 1;
    }
    return atype;

  } catch (e) {
    DocumentApp.getUi().alert("getType error. " + e.message + " : " + e.lineNumber);
    return 1;
  }
}

function getOwner(uemail) {
  var ssID = PropertiesService.getScriptProperties().getProperty('ssID');
  var ss = SpreadsheetApp.openById(ssID);
  var msheet = ss.getSheetByName("Members");
  var mnum = 1; // The actual member number = mnum+1
  while (msheet.getRange(mnum, mnumcol, 1, 1).getValues().toString() !== "") {
    if (msheet.getRange(mnum, emailcol, 1, 1).getValues().toString() == uemail) {
      // Found it!
      return msheet.getRange(mnum, namecol, 1, 1).getValues().toString() + " " + msheet.getRange(mnum, lnamecol, 1, 1).getValues().toString();
    }
    mnum++;
  }
  DocumentApp.getUi().alert("Could not find the member name matching the email " + uemail);
  return 1;
}

function checkname(aname) {
  var ssID = PropertiesService.getScriptProperties().getProperty('ssID');
  var ss = SpreadsheetApp.openById(ssID);
  var asheet = ss.getSheetByName("Assets");
  var anum = 2;
  while (asheet.getRange(anum, anamecol, 1, 1).getValues().toString() !== "") {
    if (asheet.getRange(anum, anamecol, 1, 1).getValues().toString() == aname) {
      // Found it!
      DocumentApp.getUi().alert("An asset with that name already exists. Please change its name if this is a new asset.");
      return 1;
    }
    anum++;
  }

  return 0;
}

function checkDoc(docID) {
  if (docID == null) {
    docID = "13VUKSZlX2kiZSXu_mh4z2FWYCK_ShGVqTDvVkushH1Q"; // Using GB's version of AV Commons Resource Template
  }
  //var ssID="1g_PYkVRCfwPG8mppek_mJzk11EaNyOfC1BFHI7RY3FM"; //GB  AV Commons App ID
  var ssID = PropertiesService.getScriptProperties().getProperty('ssID');
  var ss = SpreadsheetApp.openById(ssID);
  //var ss = SpreadsheetApp.getActiveSpreadsheet();
  try {
    var pdoc = DocumentApp.openById(docID);
    var iflag = true;// The all is good flag. 
    var ptables = pdoc.getBody().getTables();
    var numtables = ptables.length;

    //Check if a Project, to see if need to check the Project table
    var pflag = false;
    for (var tnum = 0; tnum < numtables - 1; tnum++) {
      if (ptables[tnum].getCell(0, 0).getText() == "ID") {
        var ptable = tnum;
        pflag = true;
      }
    }
    if (pflag) {
      //Check the Project table
      //Double check if it is a project table

      if (ptables[ptable].getCell(0, 1).getText() == "Name (linked)") {
        // Yep appears so. So process.
        var asheet = ss.getSheetByName("Assets");

        for (var npassets = 1; npassets < ptables[ptable].getNumRows(); npassets++) {
          var aname = ptables[ptable].getCell(npassets, 1).getText().trim();
          // Go through the Assets table and check IDs.
          // Now go through all the docs and see if this particular doc is listed in any of them.
          var anum = 2; //Asset Number
          var aflag = false;
          while (asheet.getRange(anum, anamecol, 1, 1).getValues().toString() !== "") {
            var aaname = asheet.getRange(anum, anamecol, 1, 1).getValue().toString().trim();
            if (aaname == aname) {
              // Yes a match.
              aflag = true;
              // Check if linked
              var elem = ptables[ptable].getCell(npassets, 1).editAsText();
              var rurl = elem.getLinkUrl();
              var raurl = asheet.getRange(anum, aURLcol, 1, 1).getValues().toString();
              if (rurl == "") {
                elem.setLinkUrl(raurl);
                ptables[ptable].getCell(npassets, 1).setBackgroundColor(goodcolor);
              } else if (rurl == raurl) {
                ptables[ptable].getCell(npassets, 1).setBackgroundColor(goodcolor);
              } else {
                //update the link
                elem.setLinkUrl(raurl);
                ptables[ptable].getCell(npassets, 1).setBackgroundColor(fixedcolor);
              }

              //  var alink = td.editAsText();
              // alink.setLinkUrl(asheet.getRange(anum,aURLcol,1.1).getValues().toString());
              //Make name green
              //ptables[ptable].getCell(npassets,1).setBackgroundColor(goodcolor);

              var asnum = asheet.getRange(anum, anumcol, 1, 1).getValues().toString();
              //Check if number is correct.
              var apnum = ptables[ptable].getCell(npassets, 0).getText();
              if (apnum == "") {
                //Add the num
                ptables[ptable].getCell(npassets, 0).setText(asnum);
                ptables[ptable].getCell(npassets, 0).setBackgroundColor(fixedcolor);
              } else {
                if (apnum == asnum) {
                  ptables[ptable].getCell(npassets, 0).setBackgroundColor(goodcolor);
                } else {
                  //fix it
                  ptables[ptable].getCell(npassets, 0).setText(asnum);
                  ptables[ptable].getCell(npassets, 0).setBackgroundColor(fixedcolor);
                }
              }
              var aID = asheet.getRange(anum, aIDcol, 1, 1).getValues().toString();
              //Now work out the status of the document
              var adoc = DocumentApp.openById(aID);
              var atables = adoc.getBody().getTables();
              var numatables = atables.length;
              var atable = atables[numatables - 1];
              var anumrows = atable.getNumRows();
              var astage = 1;
              var nstages = 0;
              // a is for checking through all the stages for this particular document.
              for (var a = 1; a < anumrows - 1; a++) {
                // Check to see if the stage pointer needs to be moved, by checking the comment column
                if (atable.getCell(a, awfccol).getText() !== "") {
                  //Move pointer
                  astage++;
                }
              }
              ptables[ptable].getCell(npassets, 3).setText(atable.getCell(astage, awfwcol).getText() + ": " + atable.getCell(astage, awfnamecol).getText());
              adoc.saveAndClose();
            }
            anum++;
          }

          if (!aflag) {
            //Asset has not been found
            ptables[ptable].getCell(npassets, 1).setBackgroundColor(missingcolor);
          }

        }
      }
    }


    var ptable = ptables[numtables - 1];
    // go through each member row and check member number and fix member name or if no member number work it out.
    // If Group do the same
    // If destination do the same
    // If no match then error, but go to next row.
    var msheet = ss.getSheetByName("Members");
    var gsheet = ss.getSheetByName("Groups");
    var xsheet = ss.getSheetByName("Destinations");
    var pnumrows = ptable.getNumRows();
    for (var prow = 1; prow < pnumrows; prow++) {
      ptable.getCell(prow, awfncol).setBackgroundColor("#FFFFFF");
      ptable.getCell(prow, awfnamecol).setBackgroundColor("#FFFFFF");
      ptable.getCell(prow, awftcol).setBackgroundColor("#FFFFFF");
      var ptype = ptable.getCell(prow, awftcol).getText();
      if (ptype == "M") {
        var mrow = ptable.getCell(prow, awfncol).getText();
        if (mrow == "") {
          var mflag = false;
          var mrow = 2;
          var mname = ptable.getCell(prow, awfnamecol).getText();
          var msname = msheet.getRange(mrow, namecol, 1, 1).getValues().toString() + " " + msheet.getRange(mrow, lnamecol).getValue().toString();
          msname = msname.trim();
          while (msname !== "") {

            if (msname == mname) {
              //we have a match
              ptable.getCell(prow, awfncol).setText(msheet.getRange(mrow, mnumcol, 1, 1).getValue().toString());
              ptable.getCell(prow, awfncol).setBackgroundColor(goodcolor);
              ptable.getCell(prow, awfnamecol).setBackgroundColor(goodcolor);
              ptable.getCell(prow, awftcol).setBackgroundColor(goodcolor);
              mflag = true;
              break; //break out of while
            }
            mrow++;
            var msname = msheet.getRange(mrow, namecol, 1, 1).getValues().toString() + " " + msheet.getRange(mrow, lnamecol).getValue().toString();
            msname = msname.trim();
          }
          if (!mflag) {
            iflag = false;
            ptable.getCell(prow, awfnamecol).setBackgroundColor(errorcolor);
          }
        } else {
          //Check that that member row exists
          mrow++;
          if (mrow > 0 && msheet.getRange(mrow, 1, 1, 1).getValue().toString() !== "")
            var mname = ptable.getCell(prow, awfnamecol).getText();
          var fname = msheet.getRange(mrow, namecol, 1, 1).getValue().toString();
          var lname = msheet.getRange(mrow, lnamecol, 1, 1).getValue().toString();
          var msname = fname + " " + lname;
          if (mname == msname) {
            ptable.getCell(prow, awfncol).setBackgroundColor(goodcolor);
            ptable.getCell(prow, awfnamecol).setBackgroundColor(goodcolor);
            ptable.getCell(prow, awftcol).setBackgroundColor(goodcolor);
          } else {
            //Member name does not match the member number
            iflag = false;
            ptable.getCell(prow, awfnamecol).setBackgroundColor(errorcolor);
            ptable.getCell(prow, awfncol).setBackgroundColor(errorcolor);
          }
        }
      } else if (ptype == "G") {
        // Go through the process of a Group
        var grow = ptable.getCell(prow, awfncol).getText();
        if (grow == "") {
          var gflag = false;
          var grow = 2;
          var gname = ptable.getCell(prow, awfnamecol).getText();
          var gsname = gsheet.getRange(grow, grnamecol, 1, 1).getValues().toString();
          gsname = gsname.trim();
          while (gsname !== "") {

            if (gsname == gname) {
              //we have a match
              ptable.getCell(prow, awfncol).setText(gsheet.getRange(grow, grnumcol, 1, 1).getValue().toString());
              ptable.getCell(prow, awfncol).setBackgroundColor(goodcolor);
              ptable.getCell(prow, awfnamecol).setBackgroundColor(goodcolor);
              ptable.getCell(prow, awftcol).setBackgroundColor(goodcolor);
              gflag = true;
              break; //break out of while
            }
            grow++;
            var gsname = gsheet.getRange(grow, grnamecol, 1, 1).getValues().toString();
            gsname = gsname.trim();
          }
          if (!gflag) {
            iflag = false;
            ptable.getCell(prow, awfnamecol).setBackgroundColor(errorcolor);
          }
        } else {
          //Check that that member row exists
          grow++;
          if (grow > 0 && gsheet.getRange(grow, 1, 1, 1).getValue().toString() !== "")
            var gname = ptable.getCell(prow, awfnamecol).getText();
          var gsname = gsheet.getRange(grow, grnamecol, 1, 1).getValue().toString();

          if (gname == gsname) {
            ptable.getCell(prow, awfncol).setBackgroundColor(goodcolor);
            ptable.getCell(prow, awfnamecol).setBackgroundColor(goodcolor);
            ptable.getCell(prow, awftcol).setBackgroundColor(goodcolor);
          } else {
            //Member name does not match the member number
            iflag = false;
            ptable.getCell(prow, awfnamecol).setBackgroundColor(errorcolor);
            ptable.getCell(prow, awfncol).setBackgroundColor(errorcolor);
          }
        }


      } else if (ptype == "D") {
        // Go through a destination check
        var drow = ptable.getCell(prow, awfncol).getText();
        if (drow == "") {
          var dflag = false;
          var drow = 2;
          var dname = ptable.getCell(prow, awfnamecol).getText();
          var dsname = xsheet.getRange(drow, dnamecol, 1, 1).getValues().toString();
          dsname = dsname.trim();
          while (dsname !== "") {

            if (dsname == dname) {
              //we have a match
              ptable.getCell(prow, awfncol).setText(xsheet.getRange(drow, dnumcol, 1, 1).getValue().toString());
              ptable.getCell(prow, awfncol).setBackgroundColor(goodcolor);
              ptable.getCell(prow, awfnamecol).setBackgroundColor(goodcolor);
              ptable.getCell(prow, awftcol).setBackgroundColor(goodcolor);
              dflag = true;
              break; //break out of while
            }
            drow++;
            var dsname = xsheet.getRange(drow, dnamecol, 1, 1).getValues().toString();
            dsname = dsname.trim();
          }
          if (!dflag) {
            iflag = false;
            ptable.getCell(prow, awfnamecol).setBackgroundColor(errorcolor);
          }
        } else {
          //Check that that member row exists
          drow++;
          if (drow > 0 && xsheet.getRange(drow, 1, 1, 1).getValue().toString() !== "")
            var dname = ptable.getCell(prow, awfnamecol).getText();
          var dsname = xsheet.getRange(drow, dnamecol, 1, 1).getValue().toString();
          if (dname == dsname) {
            ptable.getCell(prow, awfncol).setBackgroundColor(goodcolor);
            ptable.getCell(prow, awfnamecol).setBackgroundColor(goodcolor);
            ptable.getCell(prow, awftcol).setBackgroundColor(goodcolor);
          } else {
            //Destination name does not match the destination number
            iflag = false;
            ptable.getCell(prow, awfnamecol).setBackgroundColor(errorcolor);
            ptable.getCell(prow, awfncol).setBackgroundColor(errorcolor);
          }
        }

      } else {
        //Not recognised so show an error in that spot
        iflag = false;
        ptable.getCell(prow, awftcol).setBackgroundColor(errorcolor);
      }

    }
    pdoc.saveAndClose();
    if (iflag) {
      return 0;
    } else {
      return 1;
    }
  } catch (e) {
    DocumentApp.getUi().alert("checkDoc: " + e.message + " : " + e.lineNumber);

  }
}

function processDoc(docID) {
  var ssID = PropertiesService.getScriptProperties().getProperty('ssID');
  var ss = SpreadsheetApp.openById(ssID);
  try {

    // Check the doc first
    var cflag = checkDoc(docID);
    if (cflag) {
      DocumentApp.getUi().alert("Problem with checking the doc");
    } else {
      var pflag = processaDoc(docID);
      if (cflag) {
        DocumentApp.getUi().alert("Problem with processing the doc");
      } else {
        DocumentApp.getUi().alert("Doc checked and processed");
      }
    }
  } catch (e) {
    DocumentApp.getUi().alert("processDoc error: " + e.message + " : " + e.lineNumber);

  }
}

function sendagain(docID) {
  //This will send a message again to the person.
  processaDoc(docID, true);
}


function processaDoc(docID, sgflag) {
  if (sgflag == null) {
    sgflag = false; //Set send again flag to false if missing
  }
  if (docID == null) {
    docID = "1d-gqF59R5J-Hqc1kOSOOlXvM25k3UvQkpSU1IoKiLIo";
  }

  var ssID = PropertiesService.getScriptProperties().getProperty('ssID');
  var ss = SpreadsheetApp.openById(ssID);
  var iflag = true;// The all is good flag. 
  try {
    var dsheet = ss.getSheetByName("Assets");


    // If clear check if ready to send.
    // If there is a comment, but no log in the next row, then it needs to be sent.
    var pdoc = DocumentApp.openById(docID);
    var ptables = pdoc.getBody().getTables();
    var numtables = ptables.length;
    var ptable = ptables[numtables - 1];
    var pnumrows = ptable.getNumRows();
    for (var prow = 1; prow < pnumrows - 1; prow++) {
      if (ptable.getCell(prow, awfccol).getText() !== "") {
        if (ptable.getCell(prow + 1, awfccol).getText() == "") {
          // Need to check if it goes to a person or group.
          var rtype = ptable.getCell(prow, awftcol).getText();
          if (ptable.getCell(prow, awftcol).getText() == "M") {
            //check for send again flag
            if (sgflag || ptable.getCell(prow, awflcol).getText() == "") {

              // Has not been sent to the next person so send it
              var ssheet = ss.getSheetByName("Settings");
              var message = ssheet.getRange(2, 2, 1, 1).getValues().toString();
              //replace all the bits
              var mtype = ptable.getCell(prow + 1, 0).getText();
              //Get text
              mtype = mtype.replace(/[0-9]/g, '');
              mtype = mtype.trim();

              // message = message.replace('[name]',ptable.getCell(prow+1,awfnamecol).getText());
              // message = message.replace('[initiatorname]',ptable.getCell(1,awfnamecol).getText());
              // message = message.replace('[resourcename]',pdoc.getName());
              // message = message.replace('[Checktype]',mtype);
              // message = message.replace('[resourcelink]',pdoc.getUrl());

              message = message.replace('[name]', ptable.getCell(prow + 1, awfnamecol).getText());
              message = message.replace('[name]', ptable.getCell(prow + 1, awfnamecol).getText());
              message = message.replace('[initiatorname]', ptable.getCell(1, awfnamecol).getText());
              message = message.replace('[resourcename]', pdoc.getName());
              message = message.replace('[resourcename]', pdoc.getName());
              message = message.replace('[Checktype]', mtype);
              message = message.replace('[resourcelink]', pdoc.getUrl());
              message = message.replace('[previousperson]', ptable.getCell(prow, awfnamecol).getText());
              message = message.replace('[comment]', ptable.getCell(prow, awfccol).getText());
              message = message.replace('[resourcelink]', pdoc.getUrl());


              var msheet = ss.getSheetByName("Members");
              var mnum = +ptable.getCell(prow + 1, awfncol).getText() + 1;
              var dashboard = msheet.getRange(mnum, mdurlcol, 1, 1).getValues().toString();
              //update member dashboard
              var mdID = msheet.getRange(mnum, mdidcol, 1, 1).getValues().toString();

              updateMDDoc(mdID, mnum);
              //Need to open document again.
              var pdoc = DocumentApp.openById(docID);
              var ptables = pdoc.getBody().getTables();
              var numtables = ptables.length;
              var ptable = ptables[numtables - 1];

              message = message.replace('[dashboard]', dashboard);
              //[name] 
              //[initiatorname] 
              // [resourcename]
              // [Checktype] 
              // [resourcelink]


              //var destname = msheet.getRange(ptable.getCell(prow+1,awfncol).getText(),namecol,1,1).getValues().toString();
              var subject = ssheet.getRange(3, 2, 1, 1).getValues().toString();
              var myemail = Session.getEffectiveUser().getEmail();
              var emailAddress = msheet.getRange(+ptable.getCell(prow + 1, awfncol).getText() + 1, emailcol, 1, 1).getValues().toString();
              var emessage = {
                to: emailAddress,
                subject: subject,
                htmlBody: message,
                replyTo: myemail,
                name: "AV Commons team"
                //attachments: [file2.getAs(MimeType.PDF)]
              };
              //MailApp.sendEmail(emailAddress, myemail, subject, message);
              MailApp.sendEmail(emessage);
              // Leave a log in the log column.
              ptable.getCell(prow, awflcol).setText(ptable.getCell(prow, awflcol).getText() + "Message sent to " + ptable.getCell(prow + 1, awfnamecol).getText() + " on " + Utilities.formatDate(new Date(), "GMT+10", "yyyy/MM/dd") + " ");

            } else {
              var ui = DocumentApp.getUi();
              var response = ui.alert('This document has already been processed. Do you want to send it again?', ui.ButtonSet.YES_NO);

              // Process the user's response.
              if (response == ui.Button.YES) {
                pdoc.saveAndClose()
                sendagain(docID);
                response = ui.Button.NO;
              }
            }
          } else if (ptable.getCell(prow, awftcol).getText() == "G") { // Check that it's a group
            //Process a group resource
            // For now just update the group page, and put a log message but ideally we need to check the notification setting for each group member and respond
            // appropriately
            var acom = ptable.getCell(prow, awflcol).getText();
            if (ptable.getCell(prow, awflcol).getText() == "") {
              // update the group.

              var grnum = +ptable.getCell(prow, awfncol).getText();
              var gsheet = ss.getSheetByName("Groups");
              // update the previous and new group.
              var grID = gsheet.getRange(grnum + 1, gridcol, 1, 1).getValues().toString();
              // need to update the asset first before it's closed.
              ptable.getCell(prow, awflcol).setText("Group updated");
              updateGRDashboard(grID, grnum);

              //now update the new group
              grnum = +ptable.getCell(prow + 1, awfncol).getText();
              grID = gsheet.getRange(grnum + 1, gridcol, 1, 1).getValues().toString();
              updateGRDashboard(grID, grnum);
            } else {
              // presume the group has already been updated. There is no need to send messages again since this would override user preferences. They can always check the group board.
            }
          } else if (ptable.getCell(prow, awftcol).getText() == "D") {
            // Put the resource in the destination folder
            // This needs to be written.

          } else {
            // There must be an error!
            logTechIssue("Error in processaDoc. The type is not M/G/D", "DocID=" + docID + " sgflag:" + sgflag + " prow:" + prow)
          }
        }
      }
    }

    pdoc.saveAndClose();
    if (iflag) {
      return 0;
    } else {
      return 1;
    }
  } catch (e) {
    var emess = e.stack.substring(0, 43);
    if (e.stack.substring(0, 43) !== "Exception: Document is closed, its contents") {
      logTechIssue("Error in processaDoc.", "DocID=" + docID + " sgflag:" + sgflag + " prow:" + prow);
      return 1;
    }

  }
}

function createGuideSheet() {
  //This function is no longer used. It was useful as a prototype and testing tool.
  var ssID = PropertiesService.getScriptProperties().getProperty('ssID');
  var ss = SpreadsheetApp.openById(ssID);
  try {
    var gsheet = ss.getSheetByName("Guides");
    var gnum = 1;
    var tnames = "";
    //While there are docIDs create the sheets
    while (gsheet.getRange(1 + gnum, 4, 1, 1).getValues().toString() !== "") {
      var docID = gsheet.getRange(1 + gnum, 4, 1, 1).getValues().toString();
      var docGuide = DocumentApp.openById(docID);
      var guideName = gsheet.getRange(1 + gnum, 2, 1, 1).getValues().toString();
      var guideCheckboxes = docGuide.getBody().getListItems()


      var NewSheet = ss.getSheetByName(guideName);

      if (!NewSheet) {

        NewSheet = ss.insertSheet();
        NewSheet.setName(guideName);
      }

      NewSheet.getRange(1, 1, 1, 1).setValue("Task Number");
      NewSheet.getRange(1, 2, 1, 1).setValue("Formation Step");
      NewSheet.getRange(1, 6, 1, 1).setValue("Paste doc ID here");
      var tasknum = 1;
      for (let item of guideCheckboxes) {
        var gType = item.getGlyphType();
        var gTypes = item.getAttributes();
        var gTypef = item.copy();
        gTypef.setText(" ");
        docGuide.appendListItem(gTypef);
        var gTypea = item.getAttributes();
        if (gType == null) {
          NewSheet.getRange(1 + tasknum, 1, 1, 1).setValue(tasknum);
          NewSheet.getRange(1 + tasknum, 2, 1, 1).setValue(item.getText());
          NewSheet.getRange(1 + tasknum, 3, 1, 1).setValue(item.getGlyphType());
          NewSheet.getRange(1 + tasknum, 4, 1, 1).setValue(gTypef);
          tasknum++;

        }
        //Now add the members
      }
      gnum++;
    }

  } catch (e) {
    DocumentApp.getUi().alert(e.message + " : " + e.lineNumber);
  }
  NewSheet = null;

}

function copyAVC() {
  //This function will copy all of AVCommons

}

function createGroupDashboards() {
  //This function will create the Group Dashboards in AVCommons
  var ssID = PropertiesService.getScriptProperties().getProperty('ssID');
  var ss = SpreadsheetApp.openById(ssID);
  var gsheet = ss.getSheetByName("Groups");
  var gnum = 2;
  while (gsheet.getRange(gnum, grnamecol, 1, 1).getValues().toString() !== "") {
    if (gsheet.getRange(gnum, gridcol, 1, 1).getValues().toString() == "") {
      createGroupDashboard(gnum);
    }
    gnum++;
  }
}

function createGroupDashboard(gnum) {
  //This function will create a Group Dashboard in AVCommons
  //gnum is the row number

  var ssID = PropertiesService.getScriptProperties().getProperty('ssID');
  var ss = SpreadsheetApp.openById(ssID);
  var gsheet = ss.getSheetByName("Groups");

  var gname = gsheet.getRange(gnum, grnamecol, 1, 1).getValues().toString();
  //todo: the member dashboard template could be found from the assests table. But for now it's hardcoded.
  var grID = "16sx5SbkTl6R0dBRkkbtTF0JEtamr0qnUXHoSeNoPcZY";
  var file = DriveApp.getFileById(grID);
  //Group Dashboard folder
  var folder = DriveApp.getFolderById("1jRNLVRpAG7RE1GTLnI_CT-Nx8Qt4obYm");
  var grID = file.makeCopy(gname + " Dashboard", folder).getId();
  var grdoc = DocumentApp.openById(grID);
  //Store the ID in the group sheet. Everything will work off that ID
  gsheet.getRange(gnum, gridcol, 1, 1).setValue(grID);
  //Give the dashboard the right title
  var docBody = grdoc.getBody();
  docBody.replaceText('AV COMMONS GROUP', gname.toUpperCase());
  grdoc.saveAndClose();
  updateGRDashboard(grID, gnum);
}

function updateGroupDashboards() {
  //This function will update the Group Docs in AVCommons
  var ssID = PropertiesService.getScriptProperties().getProperty('ssID');
  var ss = SpreadsheetApp.openById(ssID);
  var gsheet = ss.getSheetByName("Groups");
  var gnum = 2;
  while (gsheet.getRange(gnum, grnamecol, 1, 1).getValues().toString() !== "") {
    if (gsheet.getRange(gnum, gridcol, 1, 1).getValues().toString() !== "") {
      updateGRDashboard(gsheet.getRange(gnum, gridcol, 1, 1).getValues().toString(), gnum);
    }
    gnum++;
  }

}

function updateGRDashboard(grID, gnum) {
  //gnum is the row number not the actual group number
  // set up docID for testing.
  var mnum = 0;
  if (grID == null) {
    grID = "1_0U02OMTqlL_RoyD03ZojgJ8gzchy-ZxCyOjzbfbh60";
    //gnum = 2;//this is the row number, 
  }
  try {
    var ssID = PropertiesService.getScriptProperties().getProperty('ssID');
    var ss = SpreadsheetApp.openById(ssID);
    var gsheet = ss.getSheetByName("Groups");
    var msheet = ss.getSheetByName("Members");
    var atype = ""; //Asset Type
    if (gnum == null) {
      var gcount = 2; //This is used to work out the group number
      var fflag = false;
      while (gsheet.getRange(gcount, grnamecol, 1, 1).getValues().toString() !== "") {
        if (grID == gsheet.getRange(gcount, gridcol, 1, 1).getValues().toString()) {
          //Found it
          gnum = gcount;
          fflag = true;
          break;
        }
        gcount++;
      }
      if (!fflag) {
        return "Did not find the group number. Please report this to rjzaar@gmail.com";
      }

      if (gnum == null) {
        //Will need to work out the group number from the member's table
        return "Did not find the group number. Please report this to rjzaar@gmail.com";
      }
    }
    var grname = gsheet.getRange(gnum, grnamecol, 1, 1).getValues().toString();
    var pdoc = DocumentApp.openById(grID);

    var asheet = ss.getSheetByName("Assets");
    // Remove everything after the line
    var body = pdoc.getBody();
    var numElem = body.getNumChildren();
    for (var i = numElem - 2; i > 1; i--) {
      // Working backwards find the last horizontal rule
      var etype = body.getChild(i).asText().getText();
      if (body.getChild(i).asText().getText() == "Everything below this line is created by the system. Any edits will be lost.") {
        break;
      }
      body.getChild(i).removeFromParent();
    }
    body.appendHorizontalRule();
    body.appendParagraph("");
    //Now add the Member table
    var table = body.appendTable();
    var tr = table.appendTableRow();

    var td = tr.appendTableCell('Member No.');
    //  var mname = msheet.getRange(mnum,namecol,1,1).getValues().toString()+" "+msheet.getRange(mnum,lnamecol,1,1).getValues().toString();
    var td = tr.appendTableCell('Member Name');

    var tr = table.appendTableRow();
    //Now add all members
    //Go through the Member-Groups table and collect all the members
    var mgsheet = ss.getSheetByName("Member-Groups");
    mcount = 0;
    mgcount = 2;
    while (mgsheet.getRange(mgcount, mgmemcol, 1, 1).getValues().toString() !== "") {
      var ognum = mgsheet.getRange(mgcount, mggrocol, 1, 1).getValues().toString();
      if (gnum == Number(mgsheet.getRange(mgcount, mggrocol, 1, 1).getValues().toString()) + 1) {
        //Found it
        mnum = mgsheet.getRange(mgcount, mgmemcol, 1, 1).getValues().toString();
        mcount++;
        var tr = table.appendTableRow();
        var td = tr.appendTableCell(mnum);
        var mname = msheet.getRange(+mnum + 1, namecol, 1, 1).getValues().toString() + " " + msheet.getRange(+mnum + 1, lnamecol, 1, 1).getValues().toString();
        var td = tr.appendTableCell(mname);
      }
      mgcount++;
    }

    var tr = table.appendTableRow();
    var td = tr.appendTableCell('Last Updated');
    var dateString = Utilities.formatDate(new Date(), "GMT+10", "dd/MM:HH:mm");
    var td = tr.appendTableCell(dateString);
    table.setColumnWidth(0, 80);
    table.setColumnWidth(1, 350);
    if (mcount == 0) {
      //No members yet!
      body.appendParagraph("There are no members in this group. Dear St Anthony, please find us some members.");
    }

    body.appendParagraph("");
    body.appendParagraph("ASSETS");
    body.appendParagraph("");

    var nassets = 0;



    // Now go through all the docs and see if this particular group is listed in any of them.
    var anum = 2; //Asset Number
    // Go through the assets table.
    while (asheet.getRange(anum, aIDcol, 1, 1).getValues().toString() !== "") {
      // collect all the info for each asset.
      var aID = asheet.getRange(anum, aIDcol, 1, 1).getValues().toString();
      var adoc = DocumentApp.openById(aID);
      var atables = adoc.getBody().getTables();
      var numatables = atables.length;
      var atable = atables[numatables - 1];
      var anumrows = atable.getNumRows();
      var astage = 1;
      var nstages = 0;
      // a is for checking through all the stages for this particular document.
      var cstage = 0; //This will contain the current stage. 
      for (var a = 1; a < anumrows - 1; a++) {
        // Check to see if the stage pointer needs to be moved, by checking the comment column
        if (atable.getCell(a, awfccol).getText() !== "") {
          //Move pointer
          astage++;
        }

        // see if it's at the current stage
        if (atable.getCell(a, awfccol).getText() == "" && atable.getCell(a - 1, awfccol).getText() !== "") {
          //This is the current stage
          cstage = a;
        }

        // start collecting info if the group name is present.
        if (atable.getCell(a, awfnamecol).getText() == grname) {
          //Found a match so add it to the table
          nassets++; // nassets is the overall number of assets. This is used to create the table if needed.
          if (nassets == 1) {
            //Create the Assets Table
            var table = body.appendTable();
            var tr = table.appendTableRow();

            var td = tr.appendTableCell('ID');
            var td = tr.appendTableCell('Name (and link)');
            var td = tr.appendTableCell('Type');
            var td = tr.appendTableCell('Relevant Stage');
            //var td = tr.appendTableCell('Group Role');
            var td = tr.appendTableCell('Current Stage');
            var td = tr.appendTableCell('Assigned to');
          }
          // Now add the row
          nstages++; // nstages is the number of times this particular group is listed in that particular asset. ie it can be multiple times.
          // Add a reference for each time it is listed.
          var tr = table.appendTableRow();

          var td = tr.appendTableCell(asheet.getRange(anum, anumcol, 1.1).getValues().toString()); //add resource number
          var td = tr.appendTableCell(asheet.getRange(anum, anamecol, 1.1).getValues().toString()); //add resource name and make it a link
          var alink = td.editAsText();
          alink.setLinkUrl(asheet.getRange(anum, aURLcol, 1.1).getValues().toString());
          atype = asheet.getRange(anum, atcol, 1.1).getValues().toString().substring(0, 3);
          var td = tr.appendTableCell(atype); // add resouce type.

          // To work out what stage the document is at we need to keep track of anything in the Comment column.

          var td = tr.appendTableCell(atable.getCell(a, 0).getText()); // Record the relevant stage.
          var td = tr.appendTableCell(); // This is for the current stage. This will be overwritten later.
          var td = tr.appendTableCell(); // This is for the person assigned.

        }
      }
      // Now add in the number of times the current stage is needed.
      var snnumrows = table.getNumRows(); //table is the assets table in that group. We will be working back 
      while (nstages > 0) {

        table.getCell(snnumrows - nstages, 4).setText(atable.getCell(cstage, 0).getText()); // This is where the process column is overwritten. 
        if (table.getCell(snnumrows - nstages, 3).getText() == atable.getCell(cstage, 0).getText()) { // If it is the same stage, ie current then change the color.
          table.getCell(snnumrows - nstages, 1).setBackgroundColor(goodcolor);
        }


        nstages--;
      }

      if (nassets > 0) {
        table.setColumnWidth(0, 30);
        table.setColumnWidth(1, 150);
        table.setColumnWidth(2, 40);
        table.setColumnWidth(3, 80);
        //table.setColumnWidth(4, 80);
        table.setColumnWidth(4, 80);
        table.setColumnWidth(5, 100);
      }

      adoc.saveAndClose();
      anum++;
    }

    if (nassets == 0) {
      //Not mentioned in any assets so give that message
      body.appendParagraph("Yay! No projects to work on.");
    }
    body.appendParagraph("");

    //Save and close the document
    pdoc.saveAndClose();

  } catch (e) {
    logTechIssue("updateGRDashboard error", e.stack);

  }
}

function updateMemberGroups() {
  //For now go through the Member-Group table and make sure the names of members and groups are correct, ie just replace them all.
  try {
    var ssID = PropertiesService.getScriptProperties().getProperty('ssID');
    var ss = SpreadsheetApp.openById(ssID);
    var mgsheet = ss.getSheetByName("Member-Groups");
    var mgnum = 2;
    while (mgsheet.getRange(mgnum, 1, 1, 1).getValues().toString() !== "") {
      updateMemberGroup(mgnum, +mgsheet.getRange(mgnum, 1, 1, 1).getValues().toString() + 1, +mgsheet.getRange(mgnum, 2, 1, 1).getValues().toString() + 1);
      mgnum++;
    }
  } catch (e) {
    logTechIssue("updateMemberGroups error", e.stack);

  }

}

function updateMemberGroup(mgnum, mnum, gnum) {
  // Update a single row in the Member-Group sheet
  try {
    var ssID = PropertiesService.getScriptProperties().getProperty('ssID');
    var ss = SpreadsheetApp.openById(ssID);
    var mgsheet = ss.getSheetByName("Member-Groups");
    var gsheet = ss.getSheetByName("Groups");
    var msheet = ss.getSheetByName("Members");
    mgsheet.getRange(mgnum, 4, 1, 1).setValue(msheet.getRange(mnum, 3, 1, 1).getValues().toString() + " " + msheet.getRange(mnum, 4, 1, 1).getValues().toString());
    mgsheet.getRange(mgnum, 5, 1, 1).setValue(gsheet.getRange(gnum, 2, 1, 1).getValues().toString());
  } catch (e) {
    DocumentApp.getUi().alert(e.message + " : " + e.lineNumber);
  }
}

function updateMemberPreferences(mID) {
  // This function will update which groups the member is part of and also all notification preferences.
  try {
    var pdoc = DocumentApp.openById(mID);
    //go through the member doc tables and update the necessary sheet with the information.


    var ssID = PropertiesService.getScriptProperties().getProperty('ssID');
    var ss = SpreadsheetApp.openById(ssID);
    var mgsheet = ss.getSheetByName("Member-Groups");
    var gsheet = ss.getSheetByName("Groups");
    var msheet = ss.getSheetByName("Members");
    mgsheet.getRange(mgnum, 4, 1, 1).setValue(msheet.getRange(mnum, 3, 1, 1).getValues().toString() + " " + msheet.getRange(mnum, 4, 1, 1).getValues().toString());
    mgsheet.getRange(mgnum, 5, 1, 1).setValue(gsheet.getRange(gnum, 2, 1, 1).getValues().toString());
  } catch (e) {
    DocumentApp.getUi().alert(e.message + " : " + e.lineNumber);
  }
}

function sendShepherdEmail(ernum) {
  try {
    // For debugging
    if (ernum == null || ernum == undefined) {
      ernum = 2;
    }
    if (ss == null || ss == undefined) {
      var ssID = PropertiesService.getScriptProperties().getProperty('ssID');
      var ss = SpreadsheetApp.openById(ssID);
    }

    //This function will send the designated Shepherd an email.
    var ssheet = ss.getSheetByName("settings");
    var esheet = ss.getSheetByName("Shepherd follow up");
    var semail = ssheet.getRange(16, 2, 1, 1).getValues().toString();
    //var destname = msheet.getRange(ptable.getCell(prow+1,awfncol).getText(),namecol,1,1).getValues().toString();
    var subject = "AV Commons: Shepherd response required";
    // var myemail = Session.getEffectiveUser().getEmail();
    var message = 'Dear AV Commons Shepherd,<br\>\
We have received the following request:<br\>\
Issue number:'+ esheet.getRange(ernum, 1, 1, 1).getValues().toString() + '<br\>\
Message:'+ esheet.getRange(ernum, 2, 1, 1).getValues().toString() + '<br\>\
Link: [sheet]<br\>\
<br\>\
God Bless<br\>\
AV Commons';
    message = message.replace('[sheet]', ss.getUrl());
    var emessage = {
      to: semail,
      subject: subject,
      htmlBody: message,
      // replyTo: semail,
      name: "AV Commons"
      //attachments: [file2.getAs(MimeType.PDF)]
    };
    //MailApp.sendEmail(emailAddress, myemail, subject, message);
    MailApp.sendEmail(emessage);
    // Leave a log in the log column.
    esheet.getRange(ernum, 7, 1, 1).setValue("Message sent to " + semail + " on " + Utilities.formatDate(new Date(), "GMT+10", "yyyy/MM/dd") + " ");
  } catch (e) {
    logTechIssue("sendShepherdEmail Error", e.stack)
  }
}

function sendTechEmail(ernum) {
  try {
    // For debugging
    if (ernum == null || ernum == undefined) {
      ernum = 2;
    }
    if (ss == null || ss == undefined) {
      var ssID = PropertiesService.getScriptProperties().getProperty('ssID');
      var ss = SpreadsheetApp.openById(ssID);
    }

    //This function will send the designated Shepherd an email.
    var ssheet = ss.getSheetByName("settings");
    var esheet = ss.getSheetByName("Tech follow up");
    var semail = ssheet.getRange(17, 2, 1, 1).getValues().toString();
    //var destname = msheet.getRange(ptable.getCell(prow+1,awfncol).getText(),namecol,1,1).getValues().toString();
    var subject = "AV Commons: Tech response required";
    // var myemail = Session.getEffectiveUser().getEmail();
    var message = 'Dear AV Commons Techy person,<br\>\
We have received the following request:<br\>\
Issue number:'+ esheet.getRange(ernum, 1, 1, 1).getValues().toString() + '<br\>\
Message:'+ esheet.getRange(ernum, 2, 1, 1).getValues().toString() + '<br\>\
Link: [sheet]<br\>\
<br\>\
God Bless<br\>\
AV Commons';
    message = message.replace('[sheet]', ss.getUrl());
    var emessage = {
      to: semail,
      subject: subject,
      htmlBody: message,
      replyTo: semail,
      name: "AV Commons"
      //attachments: [file2.getAs(MimeType.PDF)]
    };
    //MailApp.sendEmail(emailAddress, myemail, subject, message);
    MailApp.sendEmail(emessage);
    // Leave a log in the log column.
    esheet.getRange(ernum, 7, 1, 1).setValue("Message sent to " + semail + " on " + Utilities.formatDate(new Date(), "GMT+10", "yyyy/MM/dd") + " ");
  } catch (e) {
    logTechIssue("sendTechEmail Error", e.stack)
  }
}

function logIssue(message, comment, snum) {
  try {
    if (snum == null || snum == undefined) {
      var message = "testing";
      var comment = "commenttest";
      var snum = 2;
    }

    //log the error
    var ernum = 2;

    var avcs = SpreadsheetApp.getActiveSpreadsheet();
    var esheet = avcs.getSheetByName("Shepherd follow up");

    while (esheet.getRange(ernum, 1, 1, 1).getValues().toString() !== "") {
      ernum++;
    }
    esheet.getRange(ernum, 1, 1, 1).setValue(ernum - 1);
    esheet.getRange(ernum, 2, 1, 1).setValue(message);
    esheet.getRange(ernum, 3, 1, 1).setValue(fsheet.getRange(snum, 2, 1, 1).getValues().toString());
    esheet.getRange(ernum, 4, 1, 1).setValue(fsheet.getRange(snum, 3, 1, 1).getValues().toString());
    esheet.getRange(ernum, 5, 1, 1).setValue(fsheet.getRange(snum, 6, 1, 1).getValues().toString());
    esheet.getRange(ernum, 6, 1, 1).setValue(comment);
    sendShepherdEmail(ernum);
  } catch (e) {
    logTechIssue("logIssue Error", e.stack)
  }
}

function logTechIssue(message, comment, snum, sheet) {
  // Don't log an error with this one since it is the logger.
  // This will log a tech issue. Only a message is actually needed.
  // This needs further development todo eg adding to all functions as a means of sending an error message.
  // Particularly if there is an error caused by its use.  
  if (message == null || message == undefined) {
    var message = "testing";
    var comment = "commenttest";
    // var snum = 2; //Member Number
  }
  if (debug) {
    Logger.log(message + " " + comment + " " + snum);
    // sheet.getRange(1, errorcol, 1, 1).setValue(e.stack);
    // HWcolor = "purple";
    // sheet.getRange(1, errorcol, 1, 1).setBackground(HWcolor);
  } else {

    //log the error
    var ernum = 2;
    var avcs = SpreadsheetApp.getActiveSpreadsheet();
    var esheet = avcs.getSheetByName("Tech follow up");

    while (esheet.getRange(ernum, 1, 1, 1).getValues().toString() !== "") {
      ernum++;
    }
    esheet.getRange(ernum, 1, 1, 1).setValue(ernum - 1);
    esheet.getRange(ernum, 2, 1, 1).setValue(message);

    if (snum !== null && snum !== undefined && sheet !== null && sheet !== undefined) {
      esheet.getRange(ernum, 3, 1, 1).setValue(sheet.getRange(snum, 3, 1, 1).getValues().toString());
      esheet.getRange(ernum, 4, 1, 1).setValue(sheet.getRange(snum, 4, 1, 1).getValues().toString());
      esheet.getRange(ernum, 5, 1, 1).setValue(sheet.getRange(snum, 7, 1, 1).getValues().toString());
    }
    if (comment !== null && comment !== undefined) {
      esheet.getRange(ernum, 6, 1, 1).setValue(comment);
    }
    sendTechEmail(ernum);
  }

}
