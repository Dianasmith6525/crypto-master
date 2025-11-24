/* =================================
------------------------------------
	Cryptocurrency - Landing Page Template
	Version: 1.0
 ------------------------------------ 
 ====================================*/


'use strict';


$(window).on('load', function() {
	/*------------------
		Preloder
	--------------------*/
	$(".loader").fadeOut(); 
	$("#preloder").delay(400).fadeOut("slow");

});

(function($) {

	/*------------------
		Navigation
	--------------------*/
	$('.responsive-bar').on('click', function(event) {
		$('.main-menu').slideToggle(400);
		event.preventDefault();
	});


	/*------------------
		Background set
	--------------------*/
	$('.set-bg').each(function() {
		var bg = $(this).data('setbg');
		$(this).css('background-image', 'url(' + bg + ')');
	});

	
	/*------------------
		Review
	--------------------*/
	var review_meta = $(".review-meta-slider");
    var review_text = $(".review-text-slider");


    review_text.on('changed.owl.carousel', function(event) {
		review_meta.trigger('next.owl.carousel');
	});

	review_meta.owlCarousel({
		loop: true,
		nav: false,
		dots: true,
		items: 3,
		center: true,
		margin: 20,
		autoplay: true,
		mouseDrag: false,
	});


	review_text.owlCarousel({
		loop: true,
		nav: true,
		dots: false,
		items: 1,
		margin: 20,
		autoplay: true,
		navText: ['<i class="ti-angle-left"><i>', '<i class="ti-angle-right"><i>'],
		animateOut: 'fadeOutDown',
    	animateIn: 'fadeInDown',
	});



	 /*------------------
		Contact Form
	--------------------*/
    $(".check-form").focus(function () {
        $(this).next("span").addClass("active");
    });
    $(".check-form").blur(function () {
        if ($(this).val() === "") {
            $(this).next("span").removeClass("active");
        }
    });

	/*------------------
		Email Subscription Form (Hero Section)
	--------------------*/
	$('.hero-subscribe-from').on('submit', function(e) {
		e.preventDefault();
		var $form = $(this);
		var email = $form.find('input[type="email"]').val();
		var csrfToken = $form.find('input[name="csrf_token"]').val();
		var $btn = $form.find('button');
		var originalText = $btn.text();

		// Disable button and show loading state
		$btn.prop('disabled', true).text('Subscribing...');

		$.ajax({
			type: 'POST',
			url: 'handle-forms.php',
			data: {
				action: 'subscribe',
				email: email,
				csrf_token: csrfToken
			},
			dataType: 'json',
			success: function(response) {
				if (response.success) {
					// Show success message with theme colors
					$form.html('<div style="color: #4CAF50; font-weight: bold; padding: 10px; background: rgba(76, 175, 80, 0.1); border-radius: 5px; border-left: 4px solid #4CAF50;">✓ ' + response.message + '</div>');
				} else {
					alert('Error: ' + response.message);
					$btn.prop('disabled', false).text(originalText);
				}
			},
			error: function(xhr) {
				var errorMsg = 'An error occurred. Please try again.';
				if (xhr.status === 403) {
					errorMsg = 'Security error. Please refresh the page and try again.';
				}
				alert(errorMsg);
				$btn.prop('disabled', false).text(originalText);
			}
		});
	});

	/*------------------
		Newsletter Subscription Form
	--------------------*/
	$('.newsletter-form').on('submit', function(e) {
		e.preventDefault();
		var $form = $(this);
		var email = $form.find('input[type="email"]').val();
		var csrfToken = $form.find('input[name="csrf_token"]').val();
		var $btn = $form.find('button');
		var originalText = $btn.text();

		$btn.prop('disabled', true).text('Subscribing...');

		$.ajax({
			type: 'POST',
			url: 'handle-forms.php',
			data: {
				action: 'subscribe',
				email: email,
				csrf_token: csrfToken
			},
			dataType: 'json',
			success: function(response) {
				if (response.success) {
					$form.html('<div style="color: #4CAF50; font-weight: bold;">✓ ' + response.message + '</div>');
				} else {
					alert('Error: ' + response.message);
					$btn.prop('disabled', false).text(originalText);
				}
			},
			error: function(xhr) {
				var errorMsg = 'An error occurred. Please try again.';
				if (xhr.status === 403) {
					errorMsg = 'Security error. Please refresh the page and try again.';
				}
				alert(errorMsg);
				$btn.prop('disabled', false).text(originalText);
			}
		});
	});

	/*------------------
		Contact Form (Contact Page)
	--------------------*/
	$('.contact-form').on('submit', function(e) {
		e.preventDefault();
		var $form = $(this);
		var $btn = $form.find('button[type="submit"]');
		var originalText = $btn.text();

		var formData = {
			action: 'contact',
			name: $form.find('input[name="name"]').val(),
			email: $form.find('input[name="email"]').val(),
			subject: $form.find('input[name="subject"]').val(),
			message: $form.find('textarea[name="message"]').val()
		};

		$btn.prop('disabled', true).text('Sending...');

		$.ajax({
			type: 'POST',
			url: 'handle-forms.php',
			data: formData,
			dataType: 'json',
			success: function(response) {
				if (response.success) {
					$form.html('<div style="color: #4CAF50; font-weight: bold; padding: 15px; background: rgba(76, 175, 80, 0.1); border-radius: 5px; border-left: 4px solid #4CAF50;">✓ ' + response.message + '</div>');
				} else {
					alert('Error: ' + response.message);
					$btn.prop('disabled', false).text(originalText);
				}
			},
			error: function() {
				alert('An error occurred. Please try again.');
				$btn.prop('disabled', false).text(originalText);
			}
		});
	});


	/*------------------
		Cryptocurrency Real-time Chart
	--------------------*/
	let cryptoChart = null;
	let selectedCrypto = 'bitcoin';
	let priceRefreshInterval = null;
	const cryptoColors = {
		bitcoin: '#667eea',
		ethereum: '#764ba2',
		litecoin: '#10b981',
		ripple: '#f59e0b'
	};

	function setPriceStatus(message, state = 'connecting') {
		const statusEl = $('#price-status');
		if (!statusEl.length) return;
		const palettes = {
			connecting: { bg: '#eef2ff', border: '#cbd5f5', text: '#4c51bf', dot: '#a0aec0' },
			live: { bg: '#ecfdf5', border: '#bbf7d0', text: '#047857', dot: '#10b981' },
			backup: { bg: '#fffbea', border: '#fde68a', text: '#92400e', dot: '#f59e0b' },
			offline: { bg: '#fef2f2', border: '#fecaca', text: '#b91c1c', dot: '#dc2626' }
		};
		const palette = palettes[state] || palettes.connecting;
		statusEl.css({ background: palette.bg, borderColor: palette.border, color: palette.text });
		statusEl.find('.status-dot').css('background', palette.dot);
		statusEl.find('span').last().text(message);
	}

	function warnIfFileProtocol() {
		if (window.location.protocol === 'file:') {
			console.warn('Live prices require a PHP server. Run "php -S localhost:8000" inside the project directory and open http://localhost:8000/index.html');
			setPriceStatus('Run via PHP server (php -S localhost:8000)', 'offline');
			$('#current-price').text('Start PHP server');
			$('#price-change').text('---');
			$('#market-cap').text('---');
			$('#volume-24h').text('---');
			$('#high-low').text('---');
			return true;
		}
		return false;
	}

	// Cryptocurrency logos and info
	const cryptoInfo = {
		bitcoin: {
			name: 'Bitcoin',
			symbol: 'BTC',
			logo: 'https://assets.coingecko.com/coins/images/1/large/bitcoin.png',
			color: '#667eea'
		},
		ethereum: {
			name: 'Ethereum',
			symbol: 'ETH',
			logo: 'https://assets.coingecko.com/coins/images/279/large/ethereum.png',
			color: '#764ba2'
		},
		litecoin: {
			name: 'Litecoin',
			symbol: 'LTC',
			logo: 'https://assets.coingecko.com/coins/images/2/large/litecoin.png',
			color: '#10b981'
		},
		ripple: {
			name: 'XRP',
			symbol: 'XRP',
			logo: 'https://assets.coingecko.com/coins/images/44/large/xrp.png',
			color: '#f59e0b'
		}
	};

	// Initialize chart on page load
	function initCryptoChart() {
		const ctx = document.getElementById('cryptoChart');
		if (!ctx) return;

		cryptoChart = new Chart(ctx, {
			type: 'line',
			data: {
				labels: [],
				datasets: [{
					label: 'Price (USD)',
					data: [],
					borderColor: cryptoColors[selectedCrypto],
					backgroundColor: 'rgba(102, 126, 234, 0.1)',
					borderWidth: 2,
					fill: true,
					tension: 0.4,
					pointRadius: 0,
					pointHoverRadius: 8,
					pointBackgroundColor: cryptoColors[selectedCrypto],
					pointBorderColor: '#fff',
					pointBorderWidth: 2
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: {
						display: true,
						labels: {
							color: '#666',
							font: { size: 12 }
						}
					}
				},
				scales: {
					y: {
						beginAtZero: false,
						grid: { color: 'rgba(0,0,0,0.05)' },
						ticks: { color: '#666' }
					},
					x: {
						grid: { display: false },
						ticks: { color: '#666' }
					}
				},
				interaction: {
					intersect: false,
					mode: 'index'
				}
			}
		});

		// Load initial data
		setPriceStatus('Connecting to live data…', 'connecting');
		fetchCryptoData('bitcoin');
		
		// Start auto-refresh of prices every second
		startPriceRefresh();
	}

	// Start auto-refresh of crypto prices every second
	function startPriceRefresh() {
		// Clear any existing interval
		if (priceRefreshInterval) {
			clearInterval(priceRefreshInterval);
		}
		
		// Fetch prices every 1 second (ONLY simple price, fast)
		priceRefreshInterval = setInterval(function() {
			fetchSimplePrice(selectedCrypto);
		}, 1000);
		
		console.log(`✓ Auto-refresh started for ${selectedCrypto} - every 1 second`);
	}
	
	// Stop auto-refresh
	function stopPriceRefresh() {
		if (priceRefreshInterval) {
			clearInterval(priceRefreshInterval);
			priceRefreshInterval = null;
			console.log('Price refresh stopped');
		}
	}

	// Fetch real-time cryptocurrency data with REAL APIs only
	function fetchCryptoData(crypto) {
		selectedCrypto = crypto;
		setPriceStatus('Connecting to live data…', 'connecting');
		
		// Get simple current price from CoinGecko (fast endpoint - REAL prices)
		fetchSimplePrice(crypto);
		
		// Get 7-day chart data once for graph (not every second)
		fetchChart7DayData(crypto);
	}
	
	// Fast price fetch using backend API (updates every second)
	function fetchSimplePrice(crypto) {
		const controller = new AbortController();
		const timeoutId = setTimeout(() => controller.abort(), 5000);
		
		// Use PHP backend proxy to fetch prices (avoids CORS issues)
		fetch(`api/get-prices.php?crypto=${crypto}`, {
			signal: controller.signal,
			headers: { 'Accept': 'application/json' }
		})
			.then(response => {
				clearTimeout(timeoutId);
				if (!response.ok) throw new Error(`HTTP Error: ${response.status}`);
				return response.json();
			})
			.then(data => {
				if (data.success && data.data) {
					updateSimpleStats(data.data, crypto);
					const statusState = data.source === 'coingecko' ? 'live' : 'backup';
					const providerLabel = data.source === 'coingecko' ? 'CoinGecko' : 'Binance';
					setPriceStatus(`Live • ${providerLabel}`, statusState);
					console.log(`✓ Real price from ${providerLabel}: ${crypto}`);
				} else {
					throw new Error(data.error || 'Invalid response');
				}
			})
			.catch(error => {
				clearTimeout(timeoutId);
				setPriceStatus('Offline • retrying…', 'offline');
				console.error(`❌ Price fetch failed for ${crypto}:`, error.message);
				$('#current-price').text('Unavailable');
				$('#price-change').text('---');
				$('#market-cap').text('---');
				$('#volume-24h').text('---');
				$('#high-low').text('---');
			});
	}
	
	// Update stats with simple price data (real-time prices every second)
	function updateSimpleStats(data, crypto) {
		const info = cryptoInfo[crypto];
		$('#stat-logo').attr('src', info.logo).attr('alt', info.name);
		
		const price = data.usd || 0;
		const marketCap = data.usd_market_cap || 0;
		const volume24h = data.usd_24h_vol || 0;
		const change24h = data.usd_24h_change || 0;
		const high24h = data.usd_24h_high || price;
		const low24h = data.usd_24h_low || price;
		
		$('#current-price').text('$' + price.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
		
		const changeColor = change24h >= 0 ? '#4CAF50' : '#dc2626';
		const arrow = change24h >= 0 ? '↑' : '↓';
		$('#price-change').html(`<span style="color: ${changeColor};">${arrow} ${Math.abs(change24h).toFixed(2)}% (24h)</span>`);
		
		$('#market-cap').text('$' + (marketCap / 1e9).toFixed(2) + 'B');
		$('#volume-24h').text('$' + (volume24h / 1e9).toFixed(2) + 'B');
		$('#high-low').text('$' + high24h.toLocaleString('en-US', { maximumFractionDigits: 2 }) + ' / $' + low24h.toLocaleString('en-US', { maximumFractionDigits: 2 }));
		
		const now = new Date();
		const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
		$('#last-update').text(timeStr);
		
		// Log successful update
		console.log(`Updated ${crypto.toUpperCase()}: $${price.toFixed(2)}`);
	}
	
	// Fetch 7-day chart data separately
	function fetchChart7DayData(crypto) {
		const cryptoMap = {
			bitcoin: 'bitcoin',
			ethereum: 'ethereum',
			litecoin: 'litecoin',
			ripple: 'ripple'
		};
		
		const cryptoId = cryptoMap[crypto];
		const controller = new AbortController();
		const timeoutId = setTimeout(() => controller.abort(), 8000);
		
		fetch(`https://api.coingecko.com/api/v3/coins/${cryptoId}/market_chart?vs_currency=usd&days=7`, {
			signal: controller.signal,
			headers: { 'Accept': 'application/json' }
		})
			.then(response => {
				clearTimeout(timeoutId);
				if (!response.ok) throw new Error(`HTTP Error: ${response.status}`);
				return response.json();
			})
			.then(data => {
				if (data && data.prices && data.prices.length > 0) {
					updateChart(data, crypto);
					console.log(`✓ Chart data from CoinGecko: ${crypto}`);
				}
			})
			.catch(error => {
				clearTimeout(timeoutId);
				console.warn('Chart data fetch failed');
			});
	}
	
	// Get approximate circulating supply for market cap calculation
	function getCryptoSupply(crypto) {
		const supplies = {
			bitcoin: 21000000,
			ethereum: 120500000,
			litecoin: 84000000,
			ripple: 99990000000 // 100 billion XRP
		};
		return supplies[crypto] || 1000000;
	}


	// Update chart with new data
	function updateChart(data, crypto) {
		if (!cryptoChart) return;

		const prices = data.prices.map(p => p[1]);
		const times = data.prices.map(p => {
			const date = new Date(p[0]);
			return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
		});

		cryptoChart.data.labels = times;
		cryptoChart.data.datasets[0].data = prices;
		cryptoChart.data.datasets[0].borderColor = cryptoColors[crypto];
		cryptoChart.data.datasets[0].pointBackgroundColor = cryptoColors[crypto];
		cryptoChart.data.datasets[0].label = crypto.charAt(0).toUpperCase() + crypto.slice(1) + ' Price (USD)';
		cryptoChart.update();
	}

	// Update market stats
	function updateStats(data, crypto) {
		const prices = data.prices.map(p => p[1]);
		const current = prices[prices.length - 1];
		const previous = prices[0];
		const change = ((current - previous) / previous * 100).toFixed(2);
		const high = Math.max(...prices);
		const low = Math.min(...prices);

		// Update logo
		const info = cryptoInfo[crypto];
		$('#stat-logo').attr('src', info.logo).attr('alt', info.name);

		// Update display
		$('#current-price').text('$' + current.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
		
		const changeColor = change >= 0 ? '#4CAF50' : '#dc2626';
		const arrow = change >= 0 ? '↑' : '↓';
		$('#price-change').html(`<span style="color: ${changeColor};">${arrow} ${Math.abs(change)}% (7d)</span>`);
		
		// Market cap (approximate)
		const marketCapValue = (current * (crypto === 'bitcoin' ? 21000000 : crypto === 'ethereum' ? 120000000 : 84000000));
		$('#market-cap').text('$' + (marketCapValue / 1e9).toFixed(1) + 'B');
		
		// 24h Volume (using price range as approximation)
		const volume = (high - low) * 1000000;
		$('#volume-24h').text('$' + (volume / 1e9).toFixed(2) + 'B');
		
		// High/Low
		$('#high-low').text('$' + high.toLocaleString('en-US', { maximumFractionDigits: 2 }) + ' / $' + low.toLocaleString('en-US', { maximumFractionDigits: 2 }));
		
		// Last update
		const now = new Date();
		const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
		$('#last-update').text(timeStr);
	}

	// Crypto button click handlers
	$('.crypto-btn').on('click', function(e) {
		e.preventDefault();
		
		// Update button styles
		$('.crypto-btn').css({ 'background': 'white', 'color': $(this).css('borderColor') });
		$(this).css({ 'background': $(this).css('borderColor'), 'color': 'white' });
		
		// Fetch new data
		const crypto = $(this).data('crypto');
		fetchCryptoData(crypto);
		
		// Restart price refresh for new crypto
		startPriceRefresh();
	});

	// Newsletter subscription form handler
	function handleNewsletterSubmit(e) {
		e.preventDefault();
		
		const form = $(this);
		const email = form.find('input[name="email"]').val();
		const feedback = form.find('.form-feedback');
		const button = form.find('button[type="submit"]');
		const originalText = button.text();
		
		// Disable button and show loading state
		button.prop('disabled', true).text('Subscribing...');
		
		$.ajax({
			type: 'POST',
			url: form.attr('action'),
			data: { email: email },
			dataType: 'json',
			success: function(response) {
				feedback.css('color', response.success ? '#4CAF50' : '#f59e0b');
				feedback.text(response.message).show();
				
				if (response.success) {
					form.find('input[name="email"]').val('');
					setTimeout(function() {
						feedback.hide();
						button.prop('disabled', false).text(originalText);
					}, 5000);
				} else {
					button.prop('disabled', false).text(originalText);
				}
			},
			error: function() {
				feedback.css('color', '#f59e0b');
				feedback.text('Error subscribing. Please try again.').show();
				button.prop('disabled', false).text(originalText);
			}
		});
	}
	
	// Initialize on DOM ready
	$(document).ready(function() {
		const requiresServer = warnIfFileProtocol();
		if ($('#cryptoChart').length && !requiresServer) {
			initCryptoChart();
		}
		
		// Attach newsletter form handlers
		$('#hero-newsletter-form, #main-newsletter-form').on('submit', handleNewsletterSubmit);
	});


})(jQuery);

