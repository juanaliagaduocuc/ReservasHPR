describe('test purebas epicas clientes', () => {
  beforeEach(() => {
    cy.visit('http://localhost/HPR')
      .wait(1000)
  })

  function ingresoCliente(usuario, pass){
    cy.get('#email')
      .click()
      .wait(500)
      .type(usuario)
      .wait(500)
      .get('#password')
      .click()
      .wait(500)
      .type(pass)
      .wait(500)
      .get('#lgnBtn')
      .wait(500)
      .click()
      .wait(500)
  }

  function datosConsulta(disp,ingreso,salida,consulta,finicio,ffin) {
    cy.scrollTo(0,200)
      .get('#premium')
      .click()
      .wait(500)
      .get(disp)
      .scrollIntoView()
      .wait(500)
      .click()
      .wait(500)
      .get(ingreso)
      .click()
      .wait(500)
      .type(finicio)
      .wait(500)
      .get(salida)
      .click()
      .wait(500)
      .type(ffin)
      .wait(500)
      .get(consulta)
      .wait(500)
      .click()
      .wait(500)
      .get('.availability-summary')
      .wait(500)
  }

  function visitarInicioSinSesion() {
    cy.clearCookies()
    cy.visit('http://localhost/HPR')
  }

  function completarRegistro(email, password = 'ClienteCypress123', confirmarPassword = password) {
    cy.get('#registro-nombre').type('Cliente')
    cy.get('#registro-apellido').type('Cypress')
    cy.get('#registro-email').type(email)
    cy.get('#registro-password').type(password)
    cy.get('#registro-confirmar').type(confirmarPassword)
    cy.get('form[action="controller/registro.php"]').submit()
  }
  

  it('ver y consultar disponibilidad exito', () => {
    ingresoCliente('mfernandez@email.cl','Cliente123')
    datosConsulta('#consultar-disponibilidad-calendario-32',
      '#ingreso-32','#salida-32',
      '#consultar-disponibilidad-fechas-32',
      '2026-10-20',
      '2026-10-27'
    )
    cy.wait(500)
      .get('.availability-summary')
      .should('contain.text', 'La habitación está disponible para las fechas seleccionadas.')
  })

  it('ver y consultar disponibilidad no disponible', () => {
    ingresoCliente('mfernandez@email.cl','Cliente123')
    datosConsulta(
      '#consultar-disponibilidad-calendario-34',
      '#ingreso-34',
      '#salida-34',
      '#consultar-disponibilidad-fechas-34',
      '2026-10-06',
      '2026-10-07'
    )
    cy.wait(500)
      .get('.availability-summary')
      .should('contain.text', 'La habitación no está disponible para esas fechas. Debe dejarse un día entre estadías.')
  })

  it('lee que una habitación está ocupada en el catálogo', () => {
    ingresoCliente('mfernandez@email.cl','Cliente123')
    cy.get('.room-card.reserved')
      .first()
      .find('.room-status')
      .should('be.visible')
      .and('contain.text', 'Reservada')
  })

  it('rechaza confirmar una reserva con contraseña incorrecta', () => {
    ingresoCliente('mfernandez@email.cl','Cliente123')
    datosConsulta(
      '#consultar-disponibilidad-calendario-32',
      '#ingreso-32',
      '#salida-32',
      '#consultar-disponibilidad-fechas-32',
      '2026-10-20',
      '2026-10-27'
    )
    cy.get('#password-reserva-32').type('ClaveIncorrecta123')
    cy.get('form.booking-search')
      .contains('button', 'Confirmar reserva')
      .click()
    cy.get('.availability-summary')
      .should('contain.text', 'La contraseña ingresada no es correcta.')
    cy.get('#mensaje-reserva').should('not.exist')
  })

  it('reserva de habitacion y cancelarla ', () => {
    ingresoCliente('mfernandez@email.cl','Cliente123')
    datosConsulta('#consultar-disponibilidad-calendario-32',
      '#ingreso-32','#salida-32',
      '#consultar-disponibilidad-fechas-32',
      '2026-10-20',
      '2026-10-27')
    cy.get('form.booking-search')
      .wait(500)
      .contains('button', 'Confirmar reserva')
      .wait(500)
    cy.get('#password-reserva-32').type('Cliente123')
    cy.get('form.booking-search')
      .contains('button', 'Confirmar reserva')
      .click()
      .wait(500)
    cy.get('#mensaje-reserva')
      .scrollIntoView()
      .wait(500)
      .should('be.visible')
      .and('contain.text', '¡La reserva ha sido exitosa!')
      .wait(500)
    cy.get('#cta')
      .wait(500)
      .click()
      .wait(1000)
      .get('.cancel-button')
      .wait(1000)
      .click()
      .get('#estado-1')
      .should('contain.text','Cancelada')
  })
  
  it('ver ultima reserva', () => {
    ingresoCliente('mfernandez@email.cl','Cliente123')
    datosConsulta('#consultar-disponibilidad-calendario-32',
      '#ingreso-32','#salida-32',
      '#consultar-disponibilidad-fechas-32',
      '2026-10-20',
      '2026-10-27')
    cy.get('form.booking-search')
      .wait(500)
      .contains('button', 'Confirmar reserva')
      .wait(500)
    cy.get('#password-reserva-32').type('Cliente123')
    cy.get('form.booking-search')
      .contains('button', 'Confirmar reserva')
      .click()
      .wait(500)
    cy.get('#mensaje-reserva')
      .scrollIntoView()
      .wait(500)
      .should('be.visible')
      .and('contain.text', '¡La reserva ha sido exitosa!')
      .wait(500)
    cy.get('#cta')
      .wait(500)
      .click()
      .wait(1000)
      .get('#estado-1')
      .should('contain.text', 'Confirmada')
      .wait(500)
      .get('#estado-2')
      .should('contain.text', 'Cancelada')
      .wait(500)
  })

  it('muestra un codigo QR para cada reserva del cliente', () => {
    ingresoCliente('mfernandez@email.cl','Cliente123')
    cy.get('#cta').click()
    cy.get('tbody tr').should('exist').each(($fila) => {
      cy.wrap($fila)
        .find('.reservation-qr-details summary')
        .click()
      cy.wrap($fila)
        .find('.reservation-qr')
        .invoke('attr', 'data-reservation-qr')
        .should('include', 'Hotel Pacific Reef')
      cy.wrap($fila)
        .find('.reservation-qr canvas, .reservation-qr img')
        .should('have.length.at.least', 1)
    })
  })

  it('rechaza el inicio de sesion con credenciales incorrectas', () => {
    visitarInicioSinSesion()
    cy.get('#email').type('noexiste@email.cl')
    cy.get('#password').type('ClaveIncorrecta123')
    cy.get('#lgnBtn').click()
    cy.url().should('include', 'estado=credenciales')
    cy.get('.login-notice')
      .should('be.visible')
      .and('contain.text', 'Correo o contraseña incorrectos.')
  })

  it('registra un usuario nuevo exitosamente', () => {
    visitarInicioSinSesion()
    completarRegistro(`cypress-${Date.now()}@example.com`)
    cy.url().should('include', 'estado=registro')
    cy.get('.login-notice')
      .should('be.visible')
      .and('contain.text', 'Tu cuenta se creó correctamente.')
  })

  it('rechaza el registro con un correo ya registrado', () => {
    visitarInicioSinSesion()
    completarRegistro('mfernandez@email.cl')
    cy.url().should('include', 'estado=email-ocupado')
    cy.get('.login-notice')
      .should('be.visible')
      .and('contain.text', 'Ese correo ya está registrado.')
  })

})