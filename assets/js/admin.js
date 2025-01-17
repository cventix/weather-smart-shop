jQuery(document).ready(function ($) {
  if ($.fn.slider) {
    $("#weather-temp-range").slider({
      range: true,
      min: -50,
      max: 50,
      values: [
        parseInt($("#_weather_temp_min").val() || -10),
        parseInt($("#_weather_temp_max").val() || 40),
      ],
      slide: function (event, ui) {
        $("#_weather_temp_min").val(ui.values[0]);
        $("#_weather_temp_max").val(ui.values[1]);
        updateTemperatureDisplay(ui.values[0], ui.values[1]);
      },
    });
  }

  $(".weather-condition-item").tooltip({
    position: { my: "left+5 center", at: "right center" },
  });

  $(".weather-condition-item").on("click", function () {
    const $this = $(this);
    const condition = $this.data("condition");

    $("#_weather_condition").val(condition);

    $(".weather-condition-item").removeClass("active");
    $this.addClass("active");

    toggleConditionFields(condition);
  });

  $('input[name="_weather_price_adjust_type"]').on("change", function () {
    const type = $(this).val();
    togglePriceAdjustmentFields(type);
  });

  if ($.fn.wpColorPicker) {
    $(".weather-badge-color").wpColorPicker({
      change: function (event, ui) {
        updateBadgePreview();
      },
    });
  }

  $("#_weather_badge_text").on("input", updateBadgePreview);
  $("#_weather_badge_position").on("change", updateBadgePreview);

  function updateBadgePreview() {
    const text = $("#_weather_badge_text").val();
    const color = $("#_weather_badge_color").val();
    const position = $("#_weather_badge_position").val();

    const $preview = $("#badge-preview");
    $preview
      .css({
        backgroundColor: color,
        position: "relative",
        float: position === "left" ? "left" : "right",
      })
      .text(text);
  }

  function toggleConditionFields(condition) {
    const $tempFields = $(".weather-temperature-fields");
    const $humidityFields = $(".weather-humidity-fields");

    if (condition === "temperature") {
      $tempFields.slideDown();
      $humidityFields.slideUp();
    } else if (condition === "humidity") {
      $tempFields.slideUp();
      $humidityFields.slideDown();
    } else {
      $tempFields.slideUp();
      $humidityFields.slideUp();
    }
  }

  function togglePriceAdjustmentFields(type) {
    const $percentageField = $(".weather-price-percentage");
    const $fixedField = $(".weather-price-fixed");

    if (type === "percentage") {
      $percentageField.slideDown();
      $fixedField.slideUp();
    } else {
      $percentageField.slideUp();
      $fixedField.slideDown();
    }
  }

  function updateTemperatureDisplay(min, max) {
    $("#temp-range-display").text(`Temperature Range: ${min}°C to ${max}°C`);
  }

  $("#apply-weather-rules-bulk").on("click", function (e) {
    e.preventDefault();

    const selectedProducts = $(".product-checkbox:checked")
      .map(function () {
        return $(this).val();
      })
      .get();

    if (selectedProducts.length === 0) {
      alert("Please select at least one product");
      return;
    }

    const weatherRules = {
      condition: $("#_weather_condition").val(),
      tempMin: $("#_weather_temp_min").val(),
      tempMax: $("#_weather_temp_max").val(),
      priceAdjust: $("#_weather_price_adjust").val(),
    };

    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: {
        action: "bulk_apply_weather_rules",
        nonce: weatherSmartAdmin.nonce,
        products: selectedProducts,
        rules: weatherRules,
      },
      success: function (response) {
        if (response.success) {
          alert("Weather rules applied successfully!");
          location.reload();
        } else {
          alert("Error applying weather rules. Please try again.");
        }
      },
    });
  });

  $("#save-weather-template").on("click", function (e) {
    e.preventDefault();

    const templateName = $("#template-name").val();
    if (!templateName) {
      alert("Please enter a template name");
      return;
    }

    const templateData = {
      name: templateName,
      condition: $("#_weather_condition").val(),
      tempMin: $("#_weather_temp_min").val(),
      tempMax: $("#_weather_temp_max").val(),
      priceAdjust: $("#_weather_price_adjust").val(),
    };

    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: {
        action: "save_weather_template",
        nonce: weatherSmartAdmin.nonce,
        template: templateData,
      },
      success: function (response) {
        if (response.success) {
          alert("Template saved successfully!");
          updateTemplateList();
        } else {
          alert("Error saving template. Please try again.");
        }
      },
    });
  });

  function updateTemplateList() {
    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: {
        action: "get_weather_templates",
        nonce: weatherSmartAdmin.nonce,
      },
      success: function (response) {
        if (response.success) {
          const $select = $("#weather-template-select");
          $select.empty();

          $select.append(
            $("<option>", {
              value: "",
              text: "Select a template",
            })
          );

          response.data.forEach(function (template) {
            $select.append(
              $("<option>", {
                value: template.id,
                text: template.name,
              })
            );
          });
        }
      },
    });
  }

  $("#weather-template-select").on("change", function () {
    const templateId = $(this).val();
    if (!templateId) return;

    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: {
        action: "load_weather_template",
        nonce: weatherSmartAdmin.nonce,
        template_id: templateId,
      },
      success: function (response) {
        if (response.success) {
          const template = response.data;

          $("#_weather_condition").val(template.condition);
          $("#_weather_temp_min").val(template.tempMin);
          $("#_weather_temp_max").val(template.tempMax);
          $("#_weather_price_adjust").val(template.priceAdjust);

          // Update UI elements
          $(".weather-condition-item")
            .removeClass("active")
            .filter(`[data-condition="${template.condition}"]`)
            .addClass("active");

          toggleConditionFields(template.condition);

          if ($.fn.slider) {
            $("#weather-temp-range").slider("values", [
              template.tempMin,
              template.tempMax,
            ]);
          }

          updateTemperatureDisplay(template.tempMin, template.tempMax);
        }
      },
    });
  });

  $(".weather-help-tip").tooltip({
    content: function () {
      return $(this).prop("title");
    },
    position: { my: "left+10 center", at: "right center" },
  });
});
