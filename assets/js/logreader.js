(function($) {
  "use strict";

  function tblExport(FileFormat) {
    $("#tblDiet").tableExport({
      type: FileFormat,
      escape: "false",
      ignoreColumn: "[0,7,8]"
    });
  } // function tblExport()

  function Notepad($file) {
    var $fname = $file.parent().attr("data-filename");
    $.ajax({
      async: false,
      type: "GET",
      url: "logreader.php",
      data: "action=notepad&file=" + btoa(encodeURIComponent($fname)),
      success: function($data) {}
    });

    return true;
  } //function Notepad()

  function getParams() {
    var $params = "";

    var $filter = "";

    // POST, GET, ...
    if (
      $("#edtMethod").exists() &&
      $("#edtMethod").val() != "" &&
      $("#edtMethod").val() != null
    ) {
      $filter = $("#edtMethod")
        .val()
        .toString();
      $params += "&method=" + encodeURIComponent($filter.replace(/,/g, ";"));
    }

    // Concatenate the selected values from the select box (predifined values)
    // with manually entered values.  Concatenate values with a comma
    if ($("#edtURL").exists()) {
      $filter = "";
      if (
        $("#edtURL").exists() &&
        $("#edtURL").val() != "" &&
        $("#edtURL").val() != null
      )
        $filter = $("#edtURL")
          .val()
          .toString();
      if (
        $("#edtURLManual").exists() &&
        $("#edtURLManual").val() != "" &&
        $("#edtURLManual").val() != null
      )
        $filter +=
          "," +
          $("#edtURLManual")
            .val()
            .toString();

      if ($filter != "") {
        $filter = $filter.replace(/,/g, ";"); // The separator should be a dot-comma, not just a comma
        if ($filter.substring(0, 1) == ";") $filter = $filter.substring(1);
        $params += "&URL=" + btoa(encodeURIComponent($filter));
      }
    }

    // Concatenate the selected values from the select box (predefined values)
    // with manually entered values.  Concatenate values with a comma
    if ($("#edtReferrer").exists()) {
      $filter = "";
      if (
        $("#edtReferrer").exists() &&
        $("#edtReferrer").val() != "" &&
        $("#edtReferrer").val() != null
      )
        $filter = $("#edtReferrer")
          .val()
          .toString();
      if (
        $("#edtReferrerManual").exists() &&
        $("#edtReferrerManual").val() != "" &&
        $("#edtReferrerManual").val() != null
      )
        $filter +=
          "," +
          $("#edtReferrerManual")
            .val()
            .toString();

      if ($filter != "") {
        $filter = $filter.replace(/,/g, ";"); // The separator should be a dot-comma, not just a comma
        if ($filter.substring(0, 1) == ";") $filter = $filter.substring(1);
        $params += "&referrer=" + btoa(encodeURIComponent($filter));
      }
    }

    // Concatenate the selected values from the select box (predifined values)
    // with manually entered values.  Concatenate values with a comma
    if ($("#edtUA").exists()) {
      $filter = "";
      if (
        $("#edtUA").exists() &&
        $("#edtUA").val() != "" &&
        $("#edtUA").val() != null
      )
        $filter = $("#edtUA")
          .val()
          .toString();
      if (
        $("#edtUAManual").exists() &&
        $("#edtUAManual").val() != "" &&
        $("#edtUAManual").val() != null
      )
        $filter +=
          "," +
          $("#edtUAManual")
            .val()
            .toString();

      if ($filter != "") {
        $filter = $filter.replace(/,/g, ";"); // The separator should be a dot-comma, not just a comma
        if ($filter.substring(0, 1) == ";") $filter = $filter.substring(1);
        $params += "&userAgent=" + btoa(encodeURIComponent($filter));
      }
    }

    // Concatenate the selected values from the select box (predifined values)
    // with manually entered values.  Concatenate values with a comma
    if ($("#edtRemoteHost").exists()) {
      $filter = "";
      if (
        $("#edtRemoteHost").exists() &&
        $("#edtRemoteHost").val() != "" &&
        $("#edtRemoteHost").val() != null
      )
        $filter = $("#edtRemoteHost")
          .val()
          .toString();
      if (
        $("#edtRemoteHostManual").exists() &&
        $("#edtRemoteHostManual").val() != "" &&
        $("#edtRemoteHostManual").val() != null
      )
        $filter +=
          "," +
          $("#edtRemoteHostManual")
            .val()
            .toString();

      if ($filter != "") {
        $filter = $filter.replace(/,/g, ";"); // The separator should be a dot-comma, not just a comma
        if ($filter.substring(0, 1) == ";") $filter = $filter.substring(1);
        $params += "&remoteHost=" + btoa(encodeURIComponent($filter));
      }
    }

    if (
      $("#edtStatus").exists() &&
      $("#edtStatus").val() != "" &&
      $("#edtStatus").val() != null
    ) {
      $filter = $("#edtStatus")
        .val()
        .toString();
      $filter = $filter.replace(/,/g, ";"); // The separator should be a dot-comma, not just a comma
      $params += "&status=" + $filter;
    }

    if (
      $("#edtStartDate").exists() &&
      $("#edtStartDate").val() != "" &&
      $("#edtStartDate").val() != null
    ) {
      $params +=
        "&date=" +
        $("#edtStartDate")
          .val()
          .toString();
    }

    if (
      $("#edtEndDate").exists() &&
      $("#edtEndDate").val() != "" &&
      $("#edtEndDate").val() != null
    ) {
      $params +=
        "&enddate=" +
        $("#edtEndDate")
          .val()
          .toString();
    }

    return $params;
  } // function getParams()

  // Implement a "exists" function that will return true if a DOM object exists
  jQuery.fn.exists = function() {
    return this.length > 0;
  };

  $(document).ready(function() {
    // Fix the top of the main div; just below the navigation bar
    $("#main").css("top", $("#navBar").height() + 5);

    // Make links and manage actions
    $("[data-task]").click(function(e) {
      e.stopImmediatePropagation();
      var $task = $(this).attr("data-task");
      if ($task == undefined) return;

      var $file = "";

      if ($(this).attr("data-filename")) {
        $file = $(this).attr("data-filename");
      } else {
        $file = $(this)
          .parent()
          .parent()
          .attr("data-filename");
        if (typeof $file == "undefined")
          $file = $("#LOGFILENAME").attr("data-filename");
      }

      if ($task == "download") {
        var $type = $(this).attr("data-type");
        var $url =
          "logreader.php?action=" +
          $task +
          "&type=" +
          $type +
          "&file=" +
          btoa(encodeURIComponent($file));

        if (getParameterByName("URL") != "")
          $url += "&URL=" + getParameterByName("URL");
        if (getParameterByName("date") != "")
          $url += "&date=" + getParameterByName("date");
        if (getParameterByName("enddate") != "")
          $url += "&enddate=" + getParameterByName("enddate");
        if (getParameterByName("referrer") != "")
          $url += "&referrer=" + getParameterByName("referrer");
        if (getParameterByName("remoteHost") != "")
          $url += "&remoteHost=" + getParameterByName("remoteHost");
        if (getParameterByName("status") != "")
          $url += "&status=" + getParameterByName("status");
        if (getParameterByName("method") != "")
          $url += "&method=" + getParameterByName("method");
        if (getParameterByName("userAgent") != "")
          $url += "&userAgent=" + getParameterByName("userAgent");

        window.open($url);
      } else if ($task == "select_all" && $("#tblDiet").exists()) {
        selectAll();
      } else if ($task == "unselect_all" && $("#tblDiet").exists()) {
        unselectAll();
      } else if ($task == "select_toggle" && $("#tblDiet").exists()) {
        selectToggle();
      } else {
        // if ($task=='download') {

        var $params =
          "action=" +
          $task +
          "&file=" +
          btoa(encodeURIComponent($file)) +
          getParams();

        if (jQuery.inArray($task, ["purge", "purgeMAX", "kill"]) > -1) {
          var $div = $(this);

          $.ajax({
            async: true,
            type: "GET",
            url: "logreader.php",
            data: $params,
            beforeSend: function() {
              $div.append(
                '<span id="ajaxResult"><span class="AjaxLoading">&nbsp;</span></span>'
              );
            },
            success: function($data) {
              if ($task == "kill") {
                // Fadeout then remove
                $div
                  .parent()
                  .parent()
                  .fadeOut(300, function() {
                    $(this).remove();
                  });
              } else {
                // Purge
                // $data contains the new filesize
                $div
                  .parent()
                  .parent()
                  .find(".filesize")
                  .html('<strong class="text-success">' + $data + "</strong>");
                $div.removeClass("task");
                $div.removeAttr("data-task");
                $("#ajaxResult").remove();
              }
            }, // success
            error: function(Request, textStatus, errorThrown) {
              // Display an error message to inform the user about the problem
              var $msg =
                '<div class="bg-danger text-danger img-rounded" style="margin-top:25px;padding:10px;">';
              $msg = $msg + "<strong>An error has occured :</strong><br/>";
              $msg =
                $msg +
                "HTTP Status: " +
                Request.status +
                " (" +
                Request.statusText +
                ")<br/>";
              $msg = $msg + "Internal status: " + textStatus + "<br/>";
              $msg = $msg + "XHR ReadyState: " + Request.readyState + "<br/>";
              $msg =
                $msg +
                "Raw server response:<br/>" +
                Request.responseText +
                "<br/>";
              $msg = $msg + "</div>";
              $("#ajaxError").html($msg);
            } // error
          });
        } else {
          window.open(logReader.URL + $params, "_blank");
        }
      }
    }); // $('[data-task]').click(function()

    // ----------------------------------
    // 0. Get list of files
    // ----------------------------------

    if ($("#tblFiles").exists()) {
      // List of files
      $("#tblFiles").tablesorter({
        theme: "ice",
        widthFixed: false,
        sortMultiSortKey: "shiftKey",
        sortResetKey: "ctrlKey",
        headers: {
          0: { sorter: "text", filter: "true" }, // Filename
          1: { sorter: "digit", filter: "false" }, // FileSize
          2: { sorter: "false", filter: "false" } // Action
        },
        ignoreCase: true,
        headerTemplate: "{content} {icon}",
        widgets: ["uitheme", "filter"],
        initWidgets: true,
        sortList: [[0]] // Sort by default on the Filename
      });
    } // if ($('#tblFiles').exists())

    // ----------------------------------
    // 2. Diet
    // ----------------------------------

    if ($("#tblDiet").exists()) {
      $("#tableExportJSON").click(function(e) {
        e.stopImmediatePropagation();
        tblExport("json");
      });
      $("#tableExportCSV").click(function(e) {
        e.stopImmediatePropagation();
        tblExport("csv");
      });
      $("#tableExportDOC").click(function(e) {
        e.stopImmediatePropagation();
        tblExport("doc");
      });
      $("#tableExportXLS").click(function(e) {
        e.stopImmediatePropagation();
        tblExport("excel");
      });

      // Table with every unique records from the analyzed logfile
      //
      // The table is now filled in with every monitored sites; add the table sorting functionnalities and widget
      $("#tblDiet").tablesorter({
        theme: "ice",
        widthFixed: false,
        showProcessing: true,
        sortMultiSortKey: "shiftKey",
        sortResetKey: "ctrlKey",
        widgets: ["uitheme", "zebra", "stickyHeaders", "filter"],
        widgetOptions: {
          uitheme: "jui", //'ice',
          filter_childRows: false,
          filter_columnFilters: true,
          filter_cssFilter: "tablesorter-filter",
          filter_functions: null,
          filter_hideFilters: false,
          filter_ignoreCase: true,
          filter_reset: ".reset",
          filter_searchDelay: 300,
          filter_startsWith: false,
          filter_useParsedData: false,
          stickyHeaders: "",
          stickyHeaders_offset: 0,
          stickyHeaders_cloneId: "-sticky",
          stickyHeaders_addResizeEvent: true,
          stickyHeaders_includeCaption: true,
          stickyHeaders_zIndex: 2,
          stickyHeaders_attachTo: null,
          stickyHeaders_filteredToTop: true,
          zebra: ["ui-widget-content even", "ui-state-default odd"]
        },
        initialized: function(table) {
          // Capture click on button (all) on the form
          $("button").click(function() {
            var $task = $(this).attr("data-task");
            if (typeof $task !== typeof undefined && $task !== false) {
              switch ($task) {
                case "back":
                  document.location.href = $(this).attr("data-url");
                  break;
                case "purge":
                  document.location.href = $(this).attr("data-url");
                  break;
                case "kill":
                  document.location.href = $(this).attr("data-url");
                  break;
                case "process":
                  document.location.href = $(this).attr("data-url");
                  break;
                case "select_all":
                  selectAll();
                  break;
                case "unselect_all":
                  unselectAll();
                  break;
                case "select_toggle":
                  selectToggle();
                  break;
              }
              console.log($task);
            }
          });

          // Add the handler for the filter buttons
          $(".link-filter").click(function() {
            var filters = $("table").find("input.tablesorter-filter"),
              col = $(this).data("filter-column"),
              txt = $(this).data("filter-text");
            console.log("Filter column " + col + " on " + txt);
            filters
              .eq(col)
              .val(txt)
              .trigger("search", false);
            alertify.success("Filtre appliqué sur " + txt);
          });

          $("#frmDiet input[type=checkbox]").each(function(e) {
            // Click on the checkbox should do ... nothing since the click is handle for the <tr>
            $(this).click(function(e) {
              $(this).prop("disabled", true);
            });
          });

          // Clicking on a row = same effect than clicking on the checkbox
          $("#frmDiet tr").each(function(e) {
            // Only for rows in the body part of the table, not in the header (thead) or footer (tfoot)
            if ($(this).parent("tbody").length != 0) {
              $(this).click(function(e) {
                var $name = this.id;
                var $id = $name.replace("row", "");
                var $nbr = parseInt($("#nbrSelected").html());
                var $bytes = parseInt($("#nbrBytesIntern").html());
                var $state = !$("#chk" + $id).prop("checked");
                $("#chk" + $id).prop("checked", $state);
                if ($state === true) {
                  $nbr += parseInt($("#hits" + $id).html());
                  $bytes += parseInt($("#totalbytes" + $id).html());
                } else {
                  $nbr -= parseInt($("#hits" + $id).html());
                  $bytes -= parseInt($("#totalbytes" + $id).html());
                }
                updateSelected($nbr, $bytes);
              });
            } // if ($(this).parent("thead").length==0) {
          });
        },
        headers: {
          0: { sorter: "false", filter: "false" }, // Checkbox
          1: { sorter: "digit", filter: "false" }, // #
          2: { sorter: "text" }, // File extension
          3: { sorter: "text" }, // Filename/URL
          4: { sorter: "digit", filter: "false" }, // Hits
          5: { sorter: "text", filter: "true" }, // Method
          6: { sorter: "digit", filter: "true" }, // HTTP status
          7: { sorter: "digit", filter: "false" }, // Total size
          8: { sorter: "false", filter: "false" } // Human Site
        },
        ignoreCase: true,
        headerTemplate: "{content} {icon}",
        initWidgets: true,
        sortInitialOrder: "desc",
        // Sort by default on the number of hits, ",1" for descending sort, then sort on the URL (asc)
        sortList: [[4, 1], [3, 0]]
      });
    } // if ($('#tblDiet').exists())

    // ----------------------------------
    // 3. Process
    // ----------------------------------

    if ($("#tblProcess").exists()) {
      $("#btnNotepad").click(function() {
        Notepad($(this));
      });

      // Open a specific url.  Used by the filters functionalities
      $('[data-task="url"]').click(function() {
        var $url = $(this).attr("data-url");
        window.open($url);
      });

      // Make links and manage actions
      $("[data-task]").click(function() {
        var $task = $(this).attr("data-task");

        if ($task == "download") {
          var $file = $(this).attr("data-filename");
          var $type = $(this).attr("data-type");
          var $url =
            "logreader.php?action=" +
            $task +
            "&type=" +
            $type +
            "&file=" +
            btoa(encodeURIComponent($file));

          if (getParameterByName("URL") != "")
            $url += "&URL=" + getParameterByName("URL");
          if (getParameterByName("date") != "")
            $url += "&date=" + getParameterByName("date");
          if (getParameterByName("enddate") != "")
            $url += "&enddate=" + getParameterByName("enddate");
          if (getParameterByName("referrer") != "")
            $url += "&referrer=" + getParameterByName("referrer");
          if (getParameterByName("remoteHost") != "")
            $url += "&remoteHost=" + getParameterByName("remoteHost");
          if (getParameterByName("status") != "")
            $url += "&status=" + getParameterByName("status");
          if (getParameterByName("method") != "")
            $url += "&method=" + getParameterByName("method");
          if (getParameterByName("userAgent") != "")
            $url += "&userAgent=" + getParameterByName("userAgent");

          window.open($url);
        }
      });
    }

    $("#sidebar").affix({ offset: { top: 5 } });
    $('[data-toggle="popover"]').popover({
      trigger: "hover",
      placement: "top",
      html: true
    });

    alertify.set({ delay: 2000 }); // 2 seconds
  });

  Array.prototype.select = function(closure) {
    for (var n = 0; n < this.length; n++) {
      if (closure(this[n])) {
        return this[n];
      }
    }
    return null;
  };

  function getParameterByName(name) {
    name = name.replace(/[\[]/, "\\[").replace(/[\]]/, "\\]");
    var regex = new RegExp("[\\?&]" + name + "=([^&#]*)"),
      results = regex.exec(location.search);
    return results === null
      ? ""
      : decodeURIComponent(results[1].replace(/\+/g, " "));
  }

  /**
   * Check all visible rows (don't modify hidden rows due to filters)
   * @returns {undefined}
   */
  function selectAll() {
    var $nbr = parseInt($("#nbrSelected").html());
    var $bytes = parseInt($("#nbrBytesIntern").html());
    $("#frmDiet input:checkbox").each(function() {
      var name = this.id;
      var id = name.replace("chk", "");
      if ($("#row" + id).css("display") != "none") {
        if (this.checked === false) {
          this.checked = true;
          $nbr += parseInt($("#hits" + id).html());
          $bytes += parseInt($("#totalbytes" + id).html());
        }
      }
    });
    updateSelected($nbr, $bytes);
    return;
  }

  /**
   * Uncheck all visible rows (don't modify hidden rows due to filters)
   * @returns {undefined}
   */
  function unselectAll() {
    var $nbr = parseInt($("#nbrSelected").html());
    var $bytes = parseInt($("#nbrBytesIntern").html());
    $("#frmDiet input:checkbox").each(function() {
      var $name = this.id;
      var $id = $name.replace("chk", "");
      if ($("#row" + $id).css("display") != "none") {
        if (this.checked === true) {
          this.checked = false;
          $nbr -= parseInt($("#hits" + $id).html());
          $bytes -= parseInt($("#totalbytes" + $id).html());
        }
      }
    });
    updateSelected($nbr, $bytes);
    return;
  }

  /**
   * Toggle from checked to unchecked and vice-versa
   */
  function selectToggle() {
    var $nbr = parseInt($("#nbrSelected").html());
    var $bytes = parseInt($("#nbrBytesIntern").html());

    $("#frmDiet input:checkbox").each(function() {
      var $name = this.id;
      var $id = $name.replace("chk", "");
      if ($("#row" + $id).css("display") != "none") {
        if (this.checked === false) {
          this.checked = true;
          $nbr += parseInt($("#hits" + $id).html());
          $bytes += parseInt($("#totalbytes" + $id).html());
        } else {
          this.checked = false;
          $nbr -= parseInt($("#hits" + $id).html());
          $bytes = parseInt($("#totalbytes" + $id).html());
        }
      }
    });
    updateSelected($nbr, $bytes);
    return;
  }

  function updateSelected($nbr, $bytes) {
    if ($nbr < 0) $nbr = 0;
    $("#nbrSelected").html($nbr);
    $("#nbrBytesDisplay").html(formatBytes($bytes, 2));
    $("#nbrBytesIntern").html($bytes);
    var $total = parseInt($("#nbrRow").html());
    var $pct = 0;
    if ($nbr > 0) $pct = ($nbr / $total) * 100;
    $("#nbrSelectedPct").html($pct.toFixed(2) + "%");
    alertify.log(
      "Selected number of lines #" + $nbr + " (" + $pct.toFixed(2) + "%)",
      1
    );
  }

  function HideAllExceptThisClass(divName, className) {
    $("#" + divName + " div").each(function() {
      if (!$(this).hasClass(className)) {
        $(this).hide();
      }
    });
  }

  // http://stackoverflow.com/questions/15900485/correct-way-to-convert-size-in-bytes-to-kb-mb-gb-in-javascript
  function formatBytes(bytes, decimals) {
    if (bytes == 0) return "0 Byte";
    var k = 1024;
    var dm = decimals || 3;
    var sizes = ["Bytes", "KB", "MB", "GB", "TB", "PB", "EB", "ZB", "YB"];
    var i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + " " + sizes[i];
  }
})(jQuery);
