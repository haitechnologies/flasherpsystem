 

  function getCsrfToken() {
      if (typeof window.HAI_CSRF_TOKEN !== 'undefined' && window.HAI_CSRF_TOKEN) {
          return window.HAI_CSRF_TOKEN;
      }
      var csrfInput = document.querySelector('input[name="csrf_token"]');
      return csrfInput ? csrfInput.value : '';
  }

  function showModalError(elId, message) {
      var el = document.getElementById(elId);
      if (el) {
          el.style.display = 'block';
          el.innerHTML = message;
      }
  }

  function hideModalRobust(modalId) {
      var el = document.getElementById(modalId);
      if (!el) return;
      try {
          if (window.bootstrap && window.bootstrap.Modal) {
              window.bootstrap.Modal.getOrCreateInstance(el).hide();
              return;
          }
      } catch (e) {}
      try {
          if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
              window.jQuery('#' + modalId).modal('hide');
              return;
          }
      } catch (e) {}
      el.classList.remove('show');
      el.setAttribute('aria-hidden', 'true');
      el.style.display = 'none';
      document.body.classList.remove('modal-open');
      var backdrop = document.querySelector('.modal-backdrop');
      if (backdrop) backdrop.remove();
  }

  /*
  |--------------------------------------------------------------------------
  | 	Populate Service
  |--------------------------------------------------------------------------
  |
  */

  function ajax_populate_services(){
      var xhr;
      
      if (window.XMLHttpRequest) { // Mozilla, Safari, ...
        xhr = new XMLHttpRequest();
      
      } else if (window.ActiveXObject) { // IE 8 and older
        xhr = new ActiveXObject("Microsoft.XMLHTTP");
      
      }

      var data = "ajax_action=populate_services&csrf_token=" + encodeURIComponent(getCsrfToken());
      xhr.open("POST", "internal_request.php", true);
      xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
      xhr.send(data);

      xhr.onreadystatechange = populate_services_;

    function populate_services_() {
      if (xhr.readyState == 4) {
        if (xhr.status == 200) {
          var response        = xhr.responseText; //alert(xhr.responseText);
            // console.log(xhr.responseText);

            var row_number = document.getElementById('total_rows').value;
            // EMPTY THE DROP DOWN
            document.getElementById("service"+row_number).options.length = 0;

            const data = JSON.parse(xhr.responseText);
            // // console.log (data);

            var len = Object.keys(data).length;
            // // console.log (len);

            let option;
            var select = document.getElementById("service"+row_number);
            // select.options[select.options.length] = new Option('Please select', '0');
            select.options[select.options.length] = new Option('', '0');

            if (len > 0){
              
              for (var i = 0; i < len; i++) {
                
                  var id                = data[i].id;
                  var service_name      = data[i].service_name;
                  
                  select.options[select.options.length] = new Option(service_name, id);
              }
            }

        } else {
          console.log('There was a problem with the request.');

        }
      }
    }

  }
  
  

  /*
  |--------------------------------------------------------------------------
  | 	Populate Item Rate
  |--------------------------------------------------------------------------
  |
  */

  function ajax_populate_item_rate(item_id, row_no){
      var xhr;

      var item_id = item_id;
      var row_no = row_no;

      if (window.XMLHttpRequest) { // Mozilla, Safari, ...
        xhr = new XMLHttpRequest();
      
      } else if (window.ActiveXObject) { // IE 8 and older
        xhr = new ActiveXObject("Microsoft.XMLHTTP");
      
      }

      var data = "ajax_action=populate_item_rate&item_id="+item_id+"&row_no="+row_no+"&csrf_token="+encodeURIComponent(getCsrfToken());
      xhr.open("POST", "internal_request.php", true);
      xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
      xhr.send(data);

      xhr.onreadystatechange = populate_item_rate_;

    function populate_item_rate_() {
      if (xhr.readyState == 4) {
        if (xhr.status == 200) {
          var response        = xhr.responseText; //alert(xhr.responseText);
            // console.log(xhr.responseText);
            
            const data = JSON.parse(xhr.responseText);
            
            var item_rate = data['item_rate'];
            var row_no = data['row_no'];
            
            if (item_rate == null || item_rate == 'undefined' || item_rate == ''){
              item_rate = 0;
            }
            
            // LOAD SERVICE DETAILS
            document.getElementById('qty' + row_no).value = 1;

            document.getElementById('tax' + row_no).value = '0';
            document.getElementById('tax' + row_no).text = '0%';
            document.getElementById('tax_amount' + row_no).value = '0';
            // document.getElementById('span_tax_amount' + row_no).style.display = 'none';
            document.getElementById('div_tax_amount' + row_no).style.display = 'none';

            // document.getElementById('tax' + row_no).value = '0';
            // var selectElement = document.getElementById('tax' + row_no);
            // selectElement.options[selectElement.selectedIndex].text = '0%';
            
            document.getElementById('sub_total' + row_no).value = item_rate;
            document.getElementById('rate' + row_no).value = item_rate;
            document.getElementById('total' + row_no).value = item_rate;

            calculateItemAmount(row_no);
            // updateQty(row_no);

        } else {
          console.log('There was a problem with the request.');

        }
      }
    }

  }

  
  /*
  |--------------------------------------------------------------------------
  | 	Add Shipper
  |--------------------------------------------------------------------------
  |
  */

  function ajax_add_shipper(
                shipper_name, shipper_address_line1, shipper_address_line2,
                shipper_city, shipper_zipcode, shipper_province, shipper_country,
                shipper_email, shipper_telephone, shipper_mobile, shipper_fax){
      var xhr;
      
      if (window.XMLHttpRequest) { // Mozilla, Safari, ...
        xhr = new XMLHttpRequest();
      
      } else if (window.ActiveXObject) { // IE 8 and older
        xhr = new ActiveXObject("Microsoft.XMLHTTP");
      
      }

      var data = "ajax_action=add_shipper&shipper_name="+encodeURIComponent(shipper_name)+"&shipper_address_line1="+encodeURIComponent(shipper_address_line1)+"&shipper_address_line2="+encodeURIComponent(shipper_address_line2)+"&shipper_city="+encodeURIComponent(shipper_city)+"&shipper_zipcode="+encodeURIComponent(shipper_zipcode)+"&shipper_province="+encodeURIComponent(shipper_province)+"&shipper_country="+encodeURIComponent(shipper_country)+"&shipper_email="+encodeURIComponent(shipper_email)+"&shipper_telephone="+encodeURIComponent(shipper_telephone)+"&shipper_mobile="+encodeURIComponent(shipper_mobile)+"&shipper_fax="+encodeURIComponent(shipper_fax)+"&csrf_token="+encodeURIComponent(getCsrfToken());
      xhr.open("POST", "internal_request.php", true);
      xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
      xhr.send(data);

      xhr.onreadystatechange = add_shipper_;

    function add_shipper_() {
      if (xhr.readyState == 4) {
        if (xhr.status == 200) {
          var response        = xhr.responseText;

          var data;
          try {
            data = JSON.parse(response);
          } catch (e) {
            showModalError('ajax_shipper_error_message', 'Invalid server response. Please try again.');
            console.error('JSON parse error', e, response);
            return;
          }

          if (data.error !== undefined && data.error) {
            showModalError('ajax_shipper_error_message', data.error);
            return;
          }

          let error_message = data.error_message;
          let shipper_id    = data.shipper_id;
          let shipper_name  = data.shipper_name;

            if (error_message !== '') {
                showModalError('ajax_shipper_error_message', error_message);

            } else {
                let dropdown = document.getElementById("shipper_id");

                if (dropdown && shipper_id && shipper_name) {
                    let option = document.createElement("option");
                    option.value = shipper_id;
                    option.textContent = shipper_name;

                    dropdown.appendChild(option);

                    dropdown.value = shipper_id;

                    hideModalRobust('shipperModal');
                } else {
                    showModalError('ajax_shipper_error_message', 'Could not add the shipper. Please check the details and try again.');
                }

            }


        } else {
          showModalError('ajax_shipper_error_message', 'There was a problem with the request. (HTTP ' + xhr.status + ')');

        }
      }
    }

  }


  
  /*
  |--------------------------------------------------------------------------
  | 	Add Consignee
  |--------------------------------------------------------------------------
  |
  */

  function ajax_add_consignee(
                consignee_name, consignee_address_line1, consignee_address_line2,
                consignee_city, consignee_zipcode, consignee_province, consignee_country,
                consignee_email, consignee_telephone, consignee_mobile, consignee_fax){
      var xhr;
      
      if (window.XMLHttpRequest) { // Mozilla, Safari, ...
        xhr = new XMLHttpRequest();
      
      } else if (window.ActiveXObject) { // IE 8 and older
        xhr = new ActiveXObject("Microsoft.XMLHTTP");
      
      }

      var data = "ajax_action=add_consignee&consignee_name="+encodeURIComponent(consignee_name)+"&consignee_address_line1="+encodeURIComponent(consignee_address_line1)+"&consignee_address_line2="+encodeURIComponent(consignee_address_line2)+"&consignee_city="+encodeURIComponent(consignee_city)+"&consignee_zipcode="+encodeURIComponent(consignee_zipcode)+"&consignee_province="+encodeURIComponent(consignee_province)+"&consignee_country="+encodeURIComponent(consignee_country)+"&consignee_email="+encodeURIComponent(consignee_email)+"&consignee_telephone="+encodeURIComponent(consignee_telephone)+"&consignee_mobile="+encodeURIComponent(consignee_mobile)+"&consignee_fax="+encodeURIComponent(consignee_fax)+"&csrf_token="+encodeURIComponent(getCsrfToken());
      xhr.open("POST", "internal_request.php", true);
      xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
      xhr.send(data);

      xhr.onreadystatechange = add_consignee_;

    function add_consignee_() {
      if (xhr.readyState == 4) {
        if (xhr.status == 200) {
          var response        = xhr.responseText;

          var data;
          try {
            data = JSON.parse(response);
          } catch (e) {
            showModalError('ajax_consignee_error_message', 'Invalid server response. Please try again.');
            console.error('JSON parse error', e, response);
            return;
          }

          if (data.error !== undefined && data.error) {
            showModalError('ajax_consignee_error_message', data.error);
            return;
          }

          let error_message = data.error_message;
          let consignee_id    = data.consignee_id;
          let consignee_name  = data.consignee_name;

            if (error_message !== '') {
                showModalError('ajax_consignee_error_message', error_message);

            } else {
                let dropdown = document.getElementById("consignee_id");

                if (dropdown && consignee_id && consignee_name) {
                    let option = document.createElement("option");
                    option.value = consignee_id;
                    option.textContent = consignee_name;

                    dropdown.appendChild(option);

                    dropdown.value = consignee_id;

                    hideModalRobust('consigneeModal');
                } else {
                    showModalError('ajax_consignee_error_message', 'Could not add the consignee. Please check the details and try again.');
                }

            }


        } else {
          showModalError('ajax_consignee_error_message', 'There was a problem with the request. (HTTP ' + xhr.status + ')');

        }
      }
    }

  }


  
  /*
  |--------------------------------------------------------------------------
  | 	SELECT PORT COUNTRY
  |--------------------------------------------------------------------------
  |
  */

  function ajax_select_port_country(port_type, port_id){
      var xhr;

      
      if (window.XMLHttpRequest) { // Mozilla, Safari, ...
        xhr = new XMLHttpRequest();
      
      } else if (window.ActiveXObject) { // IE 8 and older
        xhr = new ActiveXObject("Microsoft.XMLHTTP");
      
      }

      var data = "ajax_action=select_port_country&port_type="+port_type+"&port_id="+port_id+"&csrf_token="+encodeURIComponent(getCsrfToken());
      xhr.open("POST", "internal_request.php", true);
      xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
      xhr.send(data);

      xhr.onreadystatechange = select_port_country_;

    function select_port_country_() {
      if (xhr.readyState == 4) {
        if (xhr.status == 200) {
          var response        = xhr.responseText;
           
           const data = JSON.parse(xhr.responseText);

            let port_type     = data.port_type;
            let country_id    = data.country_id;
            let country_name  = data.country_name;
            

            if (port_type !== '') {
                let dropdown = document.getElementById(""+port_type+"_country");

                if (dropdown && country_id && country_name) {
                    let option = null;
                    for (let i = 0; i < dropdown.options.length; i++) {
                        if (dropdown.options[i].value === String(country_id)) {
                            option = dropdown.options[i];
                            break;
                        }
                    }
                    if (!option) {
                        option = document.createElement("option");
                        option.value = country_id;
                        option.textContent = country_name;

                        dropdown.appendChild(option);
                    }

                    dropdown.value = country_id;

                    dropdown.disabled = false;

                    dropdown.style.pointerEvents = "";

                    dropdown.classList.remove("bg-light");

                    let hidden = document.getElementById(""+port_type+"_country_hidden");
                    if (hidden) {
                        hidden.remove();
                    }

                } else {
                    console.error("Dropdown not found or no country data");
                }

            }


        } else {
          console.log('There was a problem with the request.');

        }
      }
    }

  }


  
  /*
  |--------------------------------------------------------------------------
  | 	SELECT COUNTRY PORTS
  |--------------------------------------------------------------------------
  |
  */
  function ajax_select_country_ports(country_type) {
      const countrySelect = document.getElementById(country_type + "_country");
      const portSelect = document.getElementById(country_type + "_port");

      if (!countrySelect) {
          console.error("Dropdown elements not found for:", country_type);
          return;
      }

      const country_id = countrySelect.value;
      if (!country_id) {
          portSelect.innerHTML = "<option value=''>Select Port</option>";
          return;
      }

      const xhr = new XMLHttpRequest();
      const data = "ajax_action=select_country_ports"
                + "&country_type=" + encodeURIComponent(country_type)
                + "&country_id=" + encodeURIComponent(country_id)
                + "&csrf_token=" + encodeURIComponent(getCsrfToken());

      xhr.open("POST", "internal_request.php", true);
      xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
      xhr.send(data);

      xhr.onreadystatechange = function () {
          if (xhr.readyState === 4 && xhr.status === 200) {
              try {
                  const response = JSON.parse(xhr.responseText);

                  portSelect.innerHTML = "<option value=''>Select Port</option>";

                  response.forEach(function (port) {
                      const option = document.createElement("option");
                      option.value = port.id;
                      option.textContent = port.port_name + 
                                          (port.port_code ? " (" + port.port_code + ")" : "");
                      portSelect.appendChild(option);
                  });

              } catch (e) {
                  console.error("JSON parsing error:", e);
                  console.log(xhr.responseText);
              }
          }
      };
  }
