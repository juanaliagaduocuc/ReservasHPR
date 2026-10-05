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

})