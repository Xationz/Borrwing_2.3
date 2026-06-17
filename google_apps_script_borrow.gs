const SHEET_NAME = 'Borrowings';

const COLUMNS = [
  'borrower_name',
  'borrower_position',
  'borrower_unit',
  'borrower_phone',
  'borrower_email',
  'equipment_type',
  'borrow_quantity',
  'purpose',
  'borrow_date',
  'return_date_planned',
  'borrow_days',
  'it_install',
  'location',
  'asset_code',
];

function getSheet_() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const sheet = ss.getSheetByName(SHEET_NAME) || ss.insertSheet(SHEET_NAME);

  if (sheet.getLastRow() === 0) {
    sheet.getRange(1, 1, 1, COLUMNS.length).setValues([COLUMNS]);
  } else {
    const headers = sheet.getRange(1, 1, 1, Math.max(sheet.getLastColumn(), COLUMNS.length)).getValues()[0];
    const needsHeader = COLUMNS.some((column, index) => headers[index] !== column);
    if (needsHeader) {
      sheet.getRange(1, 1, 1, COLUMNS.length).setValues([COLUMNS]);
    }
  }

  return sheet;
}

function json_(data) {
  return ContentService
    .createTextOutput(JSON.stringify(data))
    .setMimeType(ContentService.MimeType.JSON);
}

function parsePayload_(e) {
  if (e && e.postData && e.postData.contents) {
    try {
      return JSON.parse(e.postData.contents);
    } catch (err) {
      throw new Error('Invalid JSON payload: ' + err.message);
    }
  }
  return (e && e.parameter) ? e.parameter : {};
}

function toRowData_(payload) {
  return COLUMNS.map(function(column) {
    return payload[column] == null ? '' : payload[column];
  });
}

function doGet() {
  try {
    const sheet = getSheet_();
    const lastRow = sheet.getLastRow();
    const values = lastRow > 1
      ? sheet.getRange(2, 1, lastRow - 1, COLUMNS.length).getValues()
      : [];

    const rows = values.map(function(row) {
      return COLUMNS.reduce(function(item, column, index) {
        item[column] = row[index];
        return item;
      }, {});
    });

    return json_({ success: true, columns: COLUMNS, rows: rows });
  } catch (err) {
    console.error(err);
    return json_({ success: false, error: err.message });
  }
}

function doPost(e) {
  try {
    const payload = parsePayload_(e);
    const rowData = toRowData_(payload);

    if (rowData.length !== 15) {
      throw new Error('rowData must contain exactly 15 fields (including borrower_email)');
    }

    const sheet = getSheet_();
    sheet.insertRowAfter(1);
    sheet.getRange(2, 1, 1, rowData.length).setValues([rowData]);
    return json_({ success: true, rowData: rowData });
  } catch (err) {
    console.error(err);
    return json_({ success: false, error: err.message });
  }
}
