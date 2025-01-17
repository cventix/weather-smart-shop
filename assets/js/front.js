jQuery(document).ready(function ($) {
  if ("geolocation" in navigator) {
    navigator.geolocation.getCurrentPosition(function (position) {
      const lat = position.coords.latitude;
      const lon = position.coords.longitude;
      updateWeatherData(null, lat, lon);
    });
  }

  function updateWeatherData(location = null, lat = null, lon = null) {
    const data = {
      action: "get_weather_data",
      nonce: weathersmartAjax.nonce,
    };

    if (location) {
      data.location = location;
    } else if (lat && lon) {
      data.lat = lat;
      data.lon = lon;
    }

    $.ajax({
      url: weathersmartAjax.ajaxurl,
      type: "POST",
      data: data,
      success: function (response) {
        if (response.success) {
          updateProductPrices(response.data);
          updateWeatherUI(response.data);
        }
      },
    });
  }

  function updateProductPrices(weatherData) {
    $(".weather-price").each(function () {
      const $container = $(this);
      const productId = $container.data("product-id");
      const regularPrice = $container.data("regular-price");

      $.ajax({
        url: weathersmartAjax.ajaxurl,
        type: "POST",
        data: {
          action: "calculate_weather_price",
          nonce: weathersmartAjax.nonce,
          product_id: productId,
          weather_data: weatherData,
        },
        success: function (response) {
          if (response.success) {
            updatePriceDisplay($container, response.data.price);
          }
        },
      });
    });
  }

  function updateWeatherUI(weatherData) {
    $(".weather-info").each(function () {
      const $container = $(this);
      const temp = Math.round(weatherData.main.temp);
      const condition = weatherData.weather[0].main;

      $container.find(".weather-temp").text(temp + "°C");
      $container.find(".weather-condition").text(condition);

      const iconCode = weatherData.weather[0].icon;
      const iconUrl = `http://openweathermap.org/img/w/${iconCode}.png`;
      $container.find(".weather-icon").attr("src", iconUrl);
    });
  }

  function updatePriceDisplay($container, newPrice) {
    const $priceElement = $container.find(".weather-adjusted-price");
    $priceElement.fadeOut(200, function () {
      $(this).html(newPrice).fadeIn(200);
    });
  }

  $("#weather-location-search").on("submit", function (e) {
    e.preventDefault();
    const location = $("#weather-location-input").val();
    if (location) {
      updateWeatherData(location);
    }
  });

  setInterval(function () {
    const location = $("#weather-location-input").val();
    updateWeatherData(location);
  }, 1800000); // 30 minutes
});
