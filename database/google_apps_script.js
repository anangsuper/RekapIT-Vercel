/**
 * Google Apps Script - Database API Bridge
 * Deploy ini sebagai Web App dengan akses "Anyone" (Siapa saja).
 * Masukkan URL Web App hasil deploy ke file config/database.php pada variabel $google_sheet_webapp_url.
 */

// KLIK TOMBOL "JALANKAN / RUN" PADA FUNGSI INI PERTAMA KALI UNTUK MEMBERIKAN IZIN GOOGLE DRIVE & SPREADSHEET
function runThisFirstToAuthorize() {
  DriveApp.getRootFolder();
  SpreadsheetApp.getActiveSpreadsheet();
  Logger.log("OTORISASI SUKSES! Izin Google Drive & Spreadsheet berhasil diberikan.");
}

function doGet(e) {
  var action = e.parameter.action;
  
  if (action === 'readAll') {
    return handleReadAll();
  }
  
  return ContentService.createTextOutput(JSON.stringify({ error: "Invalid action" }))
                       .setMimeType(ContentService.MimeType.JSON);
}

function doPost(e) {
  try {
    var postData = JSON.parse(e.postData.contents);
    var action = postData.action;
    var table = postData.table;
    var data = postData.data;
    
    if (action === 'insert') {
      return handleInsert(table, data);
    } else if (action === 'update') {
      return handleUpdate(table, postData.id, data);
    } else if (action === 'delete') {
      return handleDelete(table, postData.id);
    } else if (action === 'batchSync') {
      return handleBatchSync(postData.tables);
    } else if (action === 'uploadFile') {
      return handleUploadFile(postData.filename, postData.mimeType, postData.fileContent, postData.folderId);
    }
    
    return ContentService.createTextOutput(JSON.stringify({ error: "Invalid POST action" }))
                         .setMimeType(ContentService.MimeType.JSON);
  } catch (err) {
    return ContentService.createTextOutput(JSON.stringify({ error: err.message }))
                         .setMimeType(ContentService.MimeType.JSON);
  }
}

function getSheetOrCreate(name) {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var sheet = ss.getSheetByName(name);
  if (!sheet) {
    sheet = ss.insertSheet(name);
  }
  return sheet;
}

var TABLES_TO_SYNC = [
  "cabang", "divisi", "kategori_aset", "karyawan", "users", "assets", 
  "asset_history", "maintenance", "repairs", "activity_logs", 
  "asset_mutations", "audits", "sparepart", "penggunaan_sparepart"
];

function clearCache() {
  var cache = CacheService.getScriptCache();
  TABLES_TO_SYNC.forEach(function(t) {
    cache.remove("table_" + t);
  });
}

function handleReadAll() {
  var cache = CacheService.getScriptCache();
  var result = {};
  var allCached = true;
  
  // Coba ambil dari cache
  for (var i = 0; i < TABLES_TO_SYNC.length; i++) {
    var tableName = TABLES_TO_SYNC[i];
    var cachedData = cache.get("table_" + tableName);
    if (cachedData !== null) {
      try {
        result[tableName] = JSON.parse(cachedData);
      } catch (e) {
        allCached = false;
        break;
      }
    } else {
      allCached = false;
      break;
    }
  }
  
  // Jika ada cache yang kosong/missing, baca langsung dari Spreadsheet
  if (!allCached) {
    var ss = SpreadsheetApp.getActiveSpreadsheet();
    result = {};
    
    TABLES_TO_SYNC.forEach(function(tableName) {
      var sheet = ss.getSheetByName(tableName);
      if (!sheet) {
        result[tableName] = [];
        return;
      }
      
      var data = sheet.getDataRange().getValues();
      if (data.length <= 1) {
        result[tableName] = [];
        // Simpan cache kosong
        try {
          cache.put("table_" + tableName, JSON.stringify([]), 21600); // 6 jam
        } catch (err) {}
        return;
      }
      
      var headers = data[0];
      var rows = [];
      for (var i = 1; i < data.length; i++) {
        var row = {};
        var isEmpty = true;
        for (var j = 0; j < headers.length; j++) {
          var val = data[i][j];
          row[headers[j]] = val;
          if (val !== "" && val !== null && val !== undefined) {
            isEmpty = false;
          }
        }
        if (!isEmpty) {
          rows.push(row);
        }
      }
      result[tableName] = rows;
      
      // Simpan ke cache individual
      try {
        var jsonStr = JSON.stringify(rows);
        if (jsonStr.length < 100000) { // Limit 100KB per key CacheService
          cache.put("table_" + tableName, jsonStr, 21600); // 6 jam
        }
      } catch (err) {}
    });
  }
  
  return ContentService.createTextOutput(JSON.stringify(result))
                       .setMimeType(ContentService.MimeType.JSON);
}

function handleInsert(tableName, rowData) {
  clearCache();
  var sheet = getSheetOrCreate(tableName);
  var dataRange = sheet.getDataRange();
  var values = dataRange.getValues();
  var headers = [];
  
  if (values.length === 0 || values[0].length === 0 || (values.length === 1 && values[0][0] === "")) {
    // Buat header jika kosong
    headers = Object.keys(rowData);
    if (headers.indexOf('id') === -1) {
      headers.unshift('id');
    }
    sheet.appendRow(headers);
    values = [headers];
  } else {
    headers = values[0];
  }
  
  // Hitung next ID
  var idIndex = headers.indexOf('id');
  var maxId = 0;
  if (idIndex !== -1) {
    for (var i = 1; i < values.length; i++) {
      var currentId = parseInt(values[i][idIndex]);
      if (!isNaN(currentId) && currentId > maxId) {
        maxId = currentId;
      }
    }
  }
  
  var newId = maxId + 1;
  rowData['id'] = newId;
  
  var newRow = [];
  headers.forEach(function(header) {
    newRow.push(rowData[header] !== undefined ? rowData[header] : "");
  });
  
  sheet.appendRow(newRow);
  
  return ContentService.createTextOutput(JSON.stringify({ success: true, id: newId }))
                       .setMimeType(ContentService.MimeType.JSON);
}

function handleUpdate(tableName, id, rowData) {
  clearCache();
  var sheet = getSheetOrCreate(tableName);
  var values = sheet.getDataRange().getValues();
  if (values.length <= 1) {
    return ContentService.createTextOutput(JSON.stringify({ error: "Table is empty" }))
                         .setMimeType(ContentService.MimeType.JSON);
  }
  
  var headers = values[0];
  var idIndex = headers.indexOf('id');
  if (idIndex === -1) {
    return ContentService.createTextOutput(JSON.stringify({ error: "id column not found" }))
                         .setMimeType(ContentService.MimeType.JSON);
  }
  
  var targetRowIndex = -1;
  for (var i = 1; i < values.length; i++) {
    if (String(values[i][idIndex]) === String(id)) {
      targetRowIndex = i + 1; // 1-based index
      break;
    }
  }
  
  if (targetRowIndex === -1) {
    return ContentService.createTextOutput(JSON.stringify({ error: "Row with ID " + id + " not found" }))
                         .setMimeType(ContentService.MimeType.JSON);
  }
  
  // Update cells
  headers.forEach(function(header, colIndex) {
    if (header !== 'id' && rowData[header] !== undefined) {
      sheet.getRange(targetRowIndex, colIndex + 1).setValue(rowData[header]);
    }
  });
  
  return ContentService.createTextOutput(JSON.stringify({ success: true }))
                       .setMimeType(ContentService.MimeType.JSON);
}

function handleDelete(tableName, id) {
  clearCache();
  var sheet = getSheetOrCreate(tableName);
  var values = sheet.getDataRange().getValues();
  if (values.length <= 1) {
    return ContentService.createTextOutput(JSON.stringify({ error: "Table is empty" }))
                         .setMimeType(ContentService.MimeType.JSON);
  }
  
  var headers = values[0];
  var idIndex = headers.indexOf('id');
  if (idIndex === -1) {
    return ContentService.createTextOutput(JSON.stringify({ error: "id column not found" }))
                         .setMimeType(ContentService.MimeType.JSON);
  }
  
  var targetRowIndex = -1;
  for (var i = 1; i < values.length; i++) {
    if (String(values[i][idIndex]) === String(id)) {
      targetRowIndex = i + 1;
      break;
    }
  }
  
  if (targetRowIndex === -1) {
    return ContentService.createTextOutput(JSON.stringify({ error: "Row with ID " + id + " not found" }))
                         .setMimeType(ContentService.MimeType.JSON);
  }
  
  sheet.deleteRow(targetRowIndex);
  return ContentService.createTextOutput(JSON.stringify({ success: true }))
                       .setMimeType(ContentService.MimeType.JSON);
}

function handleBatchSync(tablesData) {
  clearCache();
  // Menerima map data tables dan menulis ulang seluruh sheet tersebut
  // Berguna untuk inisialisasi awal atau migrasi massal
  Object.keys(tablesData).forEach(function(tableName) {
    var sheet = getSheetOrCreate(tableName);
    sheet.clear();
    
    var rows = tablesData[tableName];
    if (rows.length === 0) return;
    
    var headers = Object.keys(rows[0]);
    sheet.appendRow(headers);
    
    var matrix = [];
    rows.forEach(function(row) {
      var rowValues = headers.map(function(h) {
        return row[h] !== null && row[h] !== undefined ? row[h] : "";
      });
      matrix.push(rowValues);
    });
    
    sheet.getRange(2, 1, matrix.length, headers.length).setValues(matrix);
  });
  
  return ContentService.createTextOutput(JSON.stringify({ success: true }))
                       .setMimeType(ContentService.MimeType.JSON);
}

/**
 * =========================================================================
 * WEBHOOK TRIGGER UNTUK SINKRONISASI OTOMATIS (MUTASI DATABASE)
 * =========================================================================
 * Catatan: Deploy script ini kembali sebagai Web App atau tambahkan trigger
 * manual pada proyek Google Apps Script Anda (Triggers > Add Trigger > onChange/onEdit).
 */
var WEBHOOK_URL = "https://rekap-it-vercel-txjt.vercel.app/api/sync.php?token=rekap_it_sync_secret_token_123";

function notifyWebApp() {
  if (!WEBHOOK_URL || WEBHOOK_URL.indexOf("[domain-website-anda]") !== -1) {
    return;
  }
  try {
    UrlFetchApp.fetch(WEBHOOK_URL, {
      "method": "get",
      "muteHttpExceptions": true
    });
  } catch (err) {
    Logger.log("Gagal mengirim notifikasi sinkronisasi ke Web App: " + err.message);
  }
}

// Fungsi Trigger otomatis saat terjadi perubahan data di spreadsheet
function onEdit(e) {
  notifyWebApp();
}

function onChange(e) {
  notifyWebApp();
}

function handleUploadFile(filename, mimeType, base64Data, folderId) {
  try {
    var decoded = Utilities.base64Decode(base64Data);
    var blob = Utilities.newBlob(decoded, mimeType, filename);
    
    var folder;
    if (folderId) {
      try {
        folder = DriveApp.getFolderById(folderId);
      } catch (err) {
        folder = DriveApp.getRootFolder();
      }
    } else {
      folder = DriveApp.getRootFolder();
    }
    
    var file = folder.createFile(blob);
    file.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW);
    
    var downloadUrl = "https://docs.google.com/uc?export=download&id=" + file.getId();
    
    return ContentService.createTextOutput(JSON.stringify({ 
      success: true, 
      id: file.getId(), 
      url: downloadUrl 
    })).setMimeType(ContentService.MimeType.JSON);
  } catch (err) {
    return ContentService.createTextOutput(JSON.stringify({ 
      success: false, 
      error: err.message 
    })).setMimeType(ContentService.MimeType.JSON);
  }
}

