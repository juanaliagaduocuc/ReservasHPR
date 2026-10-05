-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3307
-- Tiempo de generación: 06-10-2026 a las 01:22:30
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `hotel_pacific_reef`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categoria`
--

CREATE TABLE `categoria` (
  `idCategoria` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `categoria`
--

INSERT INTO `categoria` (`idCategoria`, `nombre`, `descripcion`) VALUES
(1, 'Turista', 'Habitación estándar'),
(2, 'Premium', 'Habitación premium');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cliente`
--

CREATE TABLE `cliente` (
  `idCliente` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `run` varchar(12) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `estado` tinyint(1) DEFAULT 1,
  `idRol` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cliente`
--

INSERT INTO `cliente` (`idCliente`, `nombre`, `apellido`, `run`, `email`, `telefono`, `direccion`, `password`, `estado`, `idRol`) VALUES
(1, 'Matías', 'Fernández', '77777777-7', 'mfernandez@email.cl', '961111111', 'Av. Central 123', 'Cliente123', 1, 3),
(2, 'Camila', 'Muñoz', '88888888-8', 'cmunoz@email.cl', '962222222', 'Los Olivos 456', 'Cliente123', 1, 3),
(3, 'Diego', 'Vargas', '99999999-9', 'dvargas@email.cl', '963333333', 'Pasaje Norte 789', 'Cliente123', 1, 3),
(4, 'Valentina', 'Morales', '10101010-1', 'vmorales@email.cl', '964444444', 'Villa Sur 321', 'Cliente123', 1, 3),
(5, 'Sebastián', 'Castillo', '12121212-2', 'scastillo@email.cl', '965555555', 'Las Palmas 654', 'Cliente123', 1, 3),
(6, 'Mateo', 'Alvarez', NULL, 'demo.cliente1@example.com', '+56 9 5555 0101', 'Santiago', 'HprDemo!1001', 1, 3),
(7, 'Camila', 'Rojas', NULL, 'demo.cliente2@example.com', '+56 9 5555 0102', 'Valparaiso', 'HprDemo!1002', 1, 3),
(8, 'Joaquin', 'Fuentes', NULL, 'demo.cliente3@example.com', '+56 9 5555 0103', 'Concepcion', 'HprDemo!1003', 1, 3),
(9, 'Martina', 'Vega', NULL, 'demo.cliente4@example.com', '+56 9 5555 0104', 'La Serena', 'HprDemo!1004', 1, 3),
(10, 'Sofia', 'Mendez', NULL, 'demo.cliente5@example.com', '+56 9 5555 0105', 'Puerto Montt', 'HprDemo!1005', 1, 3);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empleado`
--

CREATE TABLE `empleado` (
  `idEmpleado` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `run` varchar(12) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `estado` tinyint(1) DEFAULT 1,
  `idRol` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `empleado`
--

INSERT INTO `empleado` (`idEmpleado`, `nombre`, `apellido`, `run`, `email`, `telefono`, `password`, `estado`, `idRol`) VALUES
(1, 'Carlos', 'Gonzalez', '11111111-1', 'admin@pacificreef.cl', '912345678', 'Admin123', 0, 1),
(2, 'Juan', 'Pérez', '22222222-2', 'jperez@pacificreef.cl', '911111111', 'Empleado123', 1, 2),
(3, 'María', 'Rojas', '33333333-3', 'mrojas@pacificreef.cl', '922222222', 'Empleado123', 1, 2),
(4, 'Pedro', 'Soto', '44444444-4', 'psoto@pacificreef.cl', '933333333', 'Empleado123', 1, 2),
(5, 'Ana', 'Torres', '55555555-5', 'atorres@pacificreef.cl', '944444444', 'Empleado123', 1, 2),
(6, 'Luis', 'Contreras', '66666666-6', 'lcontreras@pacificreef.cl', '955555555', 'Empleado123', 1, 2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `habitacion`
--

CREATE TABLE `habitacion` (
  `idHabitacion` int(11) NOT NULL,
  `numero` varchar(10) NOT NULL,
  `piso` int(11) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `equipamiento` text DEFAULT NULL,
  `valorDiario` decimal(10,2) NOT NULL,
  `estado` varchar(30) DEFAULT 'Disponible',
  `idCategoria` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `habitacion`
--

INSERT INTO `habitacion` (`idHabitacion`, `numero`, `piso`, `descripcion`, `equipamiento`, `valorDiario`, `estado`, `idCategoria`) VALUES
(1, '101', 1, 'Habitación Turista', 'TV, WiFi, Aire acondicionado', 50000.00, 'Disponible', 1),
(2, '102', 1, 'Habitación Turista', 'TV, WiFi, Aire acondicionado', 50000.00, 'Disponible', 1),
(3, '103', 1, 'Habitación Turista', 'TV, WiFi, Aire acondicionado', 50000.00, 'Disponible', 1),
(4, '104', 1, 'Habitación Turista', 'TV, WiFi, Aire acondicionado', 50000.00, 'Disponible', 1),
(5, '105', 1, 'Habitación Turista', 'TV, WiFi, Aire acondicionado', 50000.00, 'Disponible', 1),
(6, '106', 1, 'Habitación Turista', 'TV, WiFi, Aire acondicionado', 50000.00, 'Disponible', 1),
(7, '201', 2, 'Habitación Turista', 'TV, WiFi, Aire acondicionado', 50000.00, 'Disponible', 1),
(8, '202', 2, 'Habitación Turista', 'TV, WiFi, Aire acondicionado', 50000.00, 'Disponible', 1),
(9, '203', 2, 'Habitación Turista', 'TV, WiFi, Aire acondicionado', 50000.00, 'Disponible', 1),
(11, '205', 2, 'Habitación Turista', 'TV, WiFi, Aire acondicionado', 50000.00, 'Disponible', 1),
(12, '206', 2, 'Habitación Turista', 'TV, WiFi, Aire acondicionado', 50000.00, 'Disponible', 1),
(13, '301', 3, 'Habitación Turista', 'TV, WiFi, Aire acondicionado', 50000.00, 'Disponible', 1),
(14, '302', 3, 'Habitación Turista', 'TV, WiFi, Aire acondicionado', 50000.00, 'Disponible', 1),
(15, '303', 3, 'Habitación Turista', 'TV, WiFi, Aire acondicionado', 50000.00, 'Disponible', 1),
(16, '304', 3, 'Habitación Turista', 'TV, WiFi, Aire acondicionado', 50000.00, 'Disponible', 1),
(17, '305', 3, 'Habitación Turista', 'TV, WiFi, Aire acondicionado', 50000.00, 'Disponible', 1),
(18, '306', 3, 'Habitación Turista', 'TV, WiFi, Aire acondicionado', 50000.00, 'Disponible', 1),
(19, '401', 4, 'Habitación Turista', 'TV, WiFi, Aire acondicionado', 50000.00, 'Disponible', 1),
(20, '402', 4, 'Habitación Turista', 'TV, WiFi, Aire acondicionado', 50000.00, 'Disponible', 1),
(21, '403', 4, 'Habitación Turista', 'TV, WiFi, Aire acondicionado', 50000.00, 'Disponible', 1),
(22, '404', 4, 'Habitación Turista', 'TV, WiFi, Aire acondicionado', 50000.00, 'Disponible', 1),
(23, '405', 4, 'Habitación Turista', 'TV, WiFi, Aire acondicionado', 50000.00, 'Disponible', 1),
(24, '406', 4, 'Habitación Turista', 'TV, WiFi, Aire acondicionado', 50000.00, 'Disponible', 1),
(25, '501', 5, 'Habitación Turista', 'TV, WiFi, Aire acondicionado', 50000.00, 'Disponible', 1),
(26, '502', 5, 'Habitación Turista', 'TV, WiFi, Aire acondicionado', 50000.00, 'Disponible', 1),
(27, '503', 5, 'Habitación Turista', 'TV, WiFi, Aire acondicionado', 50000.00, 'Disponible', 1),
(28, '504', 5, 'Habitación Turista', 'TV, WiFi, Aire acondicionado, , balcon', 50000.00, 'Disponible', 1),
(29, '505', 5, 'Habitación Turista', 'TV, WiFi, Aire acondicionado', 50000.00, 'Disponible', 1),
(30, '506', 5, 'Habitación Turista', 'TV, WiFi, Aire acondicionado', 50000.00, 'Disponible', 1),
(31, '601', 6, 'Habitación Premium', 'TV Smart, WiFi, Jacuzzi, Minibar', 120000.00, 'Disponible', 2),
(32, '602', 6, 'Habitación Premium', 'TV Smart, WiFi, Jacuzzi, Minibar', 120000.00, 'Disponible', 2),
(33, '603', 6, 'Habitación Premium', 'TV Smart, WiFi, Jacuzzi, Minibar', 120000.00, 'Disponible', 2),
(34, '604', 6, 'Habitación Premium', 'TV Smart, WiFi, Jacuzzi, Minibar', 120000.00, 'Disponible', 2),
(35, '701', 7, 'Habitación Premium', 'TV Smart, WiFi, Jacuzzi, Minibar', 120000.00, 'Disponible', 2),
(36, '702', 7, 'Habitación Premium', 'TV Smart, WiFi, Jacuzzi, Minibar', 120000.00, 'Disponible', 2),
(37, '703', 7, 'Habitación Premium', 'TV Smart, WiFi, Jacuzzi, Minibar', 120000.00, 'Disponible', 2),
(38, '704', 7, 'Habitación Premium', 'TV Smart, WiFi, Jacuzzi, Minibar', 120000.00, 'Disponible', 2),
(40, '204', 2, 'Habitacion Turista', 'TV, WiFi, Aire acondicionado', 50000.00, 'Disponible', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pago`
--

CREATE TABLE `pago` (
  `idPago` int(11) NOT NULL,
  `fechaPago` datetime NOT NULL DEFAULT current_timestamp(),
  `monto` decimal(10,2) NOT NULL,
  `estado` varchar(30) DEFAULT 'Pagado',
  `idReserva` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pago`
--

INSERT INTO `pago` (`idPago`, `fechaPago`, `monto`, `estado`, `idReserva`) VALUES
(1, '2026-09-21 13:22:10', 432000.00, 'Pagado', 1),
(2, '2026-09-21 13:22:10', 432000.00, 'Pagado', 2),
(3, '2026-09-21 13:22:10', 180000.00, 'Pagado', 3);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reserva`
--

CREATE TABLE `reserva` (
  `idReserva` int(11) NOT NULL,
  `fechaReserva` datetime NOT NULL DEFAULT current_timestamp(),
  `fechaIngreso` date NOT NULL,
  `fechaSalida` date NOT NULL,
  `cantidadDias` int(11) NOT NULL,
  `valorTotal` decimal(10,2) NOT NULL,
  `valorAnticipo` decimal(10,2) NOT NULL,
  `estado` varchar(30) DEFAULT 'Pendiente',
  `idHabitacion` int(11) NOT NULL,
  `idCliente` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `reserva`
--

INSERT INTO `reserva` (`idReserva`, `fechaReserva`, `fechaIngreso`, `fechaSalida`, `cantidadDias`, `valorTotal`, `valorAnticipo`, `estado`, `idHabitacion`, `idCliente`) VALUES
(1, '2026-09-21 13:22:10', '2026-09-21', '2026-10-03', 12, 1440000.00, 432000.00, 'Cancelada', 31, 1),
(2, '2026-09-21 13:22:10', '2026-09-21', '2026-10-03', 12, 1440000.00, 432000.00, 'Confirmada', 32, 2),
(3, '2026-09-21 13:22:10', '2026-09-21', '2026-10-03', 12, 600000.00, 180000.00, 'Confirmada', 1, 3),
(4, '2026-10-05 02:17:31', '2026-09-05', '2026-09-08', 3, 150000.00, 45000.00, 'Confirmada', 1, 6),
(5, '2026-10-05 02:17:31', '2026-09-10', '2026-09-12', 2, 100000.00, 30000.00, 'Confirmada', 2, 6),
(6, '2026-10-05 02:17:31', '2026-10-19', '2026-10-22', 3, 150000.00, 45000.00, 'Confirmada', 3, 7),
(7, '2026-10-05 02:17:31', '2026-10-26', '2026-10-28', 2, 100000.00, 30000.00, 'Confirmada', 4, 7),
(8, '2026-10-05 02:17:31', '2026-11-04', '2026-11-07', 3, 150000.00, 45000.00, 'Confirmada', 5, 8),
(9, '2026-10-05 02:17:31', '2026-11-09', '2026-11-11', 2, 100000.00, 30000.00, 'Confirmada', 6, 8),
(10, '2026-10-05 02:17:31', '2026-11-19', '2026-11-22', 3, 150000.00, 45000.00, 'Confirmada', 7, 9),
(11, '2026-10-05 02:17:31', '2026-11-29', '2026-12-01', 2, 100000.00, 30000.00, 'Confirmada', 8, 9),
(12, '2026-10-05 02:17:31', '2026-12-09', '2026-12-13', 4, 200000.00, 60000.00, 'Confirmada', 9, 10),
(13, '2026-10-05 02:17:31', '2026-12-19', '2026-12-22', 3, 150000.00, 45000.00, 'Confirmada', 11, 10),
(14, '2026-10-05 02:32:15', '2026-10-05', '2026-10-06', 1, 120000.00, 36000.00, 'Confirmada', 34, 1),
(15, '2026-10-05 17:23:11', '2026-10-05', '2026-10-06', 1, 120000.00, 36000.00, 'Confirmada', 38, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rol`
--

CREATE TABLE `rol` (
  `idRol` int(11) NOT NULL,
  `descripcion` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `rol`
--

INSERT INTO `rol` (`idRol`, `descripcion`) VALUES
(1, 'Administrador'),
(2, 'Empleado'),
(3, 'Cliente');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ticket`
--

CREATE TABLE `ticket` (
  `idTicket` int(11) NOT NULL,
  `fechaEmision` datetime NOT NULL DEFAULT current_timestamp(),
  `codigo` varchar(255) NOT NULL,
  `idReserva` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ticket`
--

INSERT INTO `ticket` (`idTicket`, `fechaEmision`, `codigo`, `idReserva`) VALUES
(1, '2026-09-21 13:22:10', 'QR-RES-000001', 1),
(2, '2026-09-21 13:22:10', 'QR-RES-000002', 2),
(3, '2026-09-21 13:22:10', 'QR-RES-000003', 3);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `categoria`
--
ALTER TABLE `categoria`
  ADD PRIMARY KEY (`idCategoria`);

--
-- Indices de la tabla `cliente`
--
ALTER TABLE `cliente`
  ADD PRIMARY KEY (`idCliente`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `run` (`run`),
  ADD KEY `fk_cliente_rol` (`idRol`);

--
-- Indices de la tabla `empleado`
--
ALTER TABLE `empleado`
  ADD PRIMARY KEY (`idEmpleado`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `run` (`run`),
  ADD KEY `fk_empleado_rol` (`idRol`);

--
-- Indices de la tabla `habitacion`
--
ALTER TABLE `habitacion`
  ADD PRIMARY KEY (`idHabitacion`),
  ADD KEY `fk_habitacion_categoria` (`idCategoria`);

--
-- Indices de la tabla `pago`
--
ALTER TABLE `pago`
  ADD PRIMARY KEY (`idPago`),
  ADD UNIQUE KEY `idReserva` (`idReserva`);

--
-- Indices de la tabla `reserva`
--
ALTER TABLE `reserva`
  ADD PRIMARY KEY (`idReserva`),
  ADD KEY `fk_reserva_habitacion` (`idHabitacion`),
  ADD KEY `fk_reserva_cliente` (`idCliente`);

--
-- Indices de la tabla `rol`
--
ALTER TABLE `rol`
  ADD PRIMARY KEY (`idRol`);

--
-- Indices de la tabla `ticket`
--
ALTER TABLE `ticket`
  ADD PRIMARY KEY (`idTicket`),
  ADD UNIQUE KEY `idReserva` (`idReserva`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `categoria`
--
ALTER TABLE `categoria`
  MODIFY `idCategoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `cliente`
--
ALTER TABLE `cliente`
  MODIFY `idCliente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `empleado`
--
ALTER TABLE `empleado`
  MODIFY `idEmpleado` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `habitacion`
--
ALTER TABLE `habitacion`
  MODIFY `idHabitacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT de la tabla `pago`
--
ALTER TABLE `pago`
  MODIFY `idPago` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `reserva`
--
ALTER TABLE `reserva`
  MODIFY `idReserva` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT de la tabla `rol`
--
ALTER TABLE `rol`
  MODIFY `idRol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `ticket`
--
ALTER TABLE `ticket`
  MODIFY `idTicket` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `cliente`
--
ALTER TABLE `cliente`
  ADD CONSTRAINT `fk_cliente_rol` FOREIGN KEY (`idRol`) REFERENCES `rol` (`idRol`);

--
-- Filtros para la tabla `empleado`
--
ALTER TABLE `empleado`
  ADD CONSTRAINT `fk_empleado_rol` FOREIGN KEY (`idRol`) REFERENCES `rol` (`idRol`);

--
-- Filtros para la tabla `habitacion`
--
ALTER TABLE `habitacion`
  ADD CONSTRAINT `fk_habitacion_categoria` FOREIGN KEY (`idCategoria`) REFERENCES `categoria` (`idCategoria`);

--
-- Filtros para la tabla `pago`
--
ALTER TABLE `pago`
  ADD CONSTRAINT `fk_pago_reserva` FOREIGN KEY (`idReserva`) REFERENCES `reserva` (`idReserva`);

--
-- Filtros para la tabla `reserva`
--
ALTER TABLE `reserva`
  ADD CONSTRAINT `fk_reserva_cliente` FOREIGN KEY (`idCliente`) REFERENCES `cliente` (`idCliente`),
  ADD CONSTRAINT `fk_reserva_habitacion` FOREIGN KEY (`idHabitacion`) REFERENCES `habitacion` (`idHabitacion`);

--
-- Filtros para la tabla `ticket`
--
ALTER TABLE `ticket`
  ADD CONSTRAINT `fk_ticket_reserva` FOREIGN KEY (`idReserva`) REFERENCES `reserva` (`idReserva`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
