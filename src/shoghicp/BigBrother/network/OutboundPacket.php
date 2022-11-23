<?php
/**
 *  ______  __         ______               __    __
 * |   __ \|__|.-----.|   __ \.----..-----.|  |_ |  |--..-----..----.
 * |   __ <|  ||  _  ||   __ <|   _||  _  ||   _||     ||  -__||   _|
 * |______/|__||___  ||______/|__|  |_____||____||__|__||_____||__|
 *             |_____|
 *
 * BigBrother plugin for PocketMine-MP
 * Copyright (C) 2014-2015 shoghicp <https://github.com/shoghicp/BigBrother>
 * Copyright (C) 2016- BigBrotherTeam
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @author BigBrotherTeam
 * @link   https://github.com/BigBrotherTeam/BigBrother
 *
 */

declare(strict_types=1);

namespace shoghicp\BigBrother\network;

use ErrorException;
use function get_class;

abstract class OutboundPacket extends Packet{

	//Play
	const ADD_ENTITY_PACKET = 0x00;
	const ADD_EXPERIENCE_ORB_PACKET = 0x01;
	const ADD_MOB_PACKET = 0x02;
	const ADD_PAINTING_PACKET = 0x03;
	const ADD_PLAYER_PACKET = 0x04;
	const ANIMATE_PACKET = 0x05;
	const AWARD_STATS_PACKET = 0x06;
	//TODO ACKNOWLEDGE_PLAYER_DIGGING_PACKET = 0x07;
	const BLOCK_DESTRUCTION_PACKET = 0x08;
	const BLOCK_ENTITY_DATA_PACKET = 0x09;
	const BLOCK_EVENT_PACKET = 0x0a;
	const BLOCK_UPDATE_PACKET = 0x0b;
	const BOSS_EVENT_PACKET = 0x0c;
	const CHANGE_DIFFICULTY_PACKET = 0x0d;
	const CHAT_PACKET = 0x0e;
	const COMMAND_SUGGESTIONS_PACKET = 0x0f;
	//TODO DECLARE_COMMANDS_PACKET = 0x10;
	const WINDOW_CONFIRMATION_PACKET = 0x11;
	const CONTAINER_CLOSE_PACKET = 0x12;
	const CONTAINER_SET_CONTENT_PACKET = 0x13;
	const CONTAINER_SET_DATA_PACKET = 0x14;
	const CONTAINER_SET_SLOT_PACKET = 0x15;
	//TODO SET_COOLDOWN_PACKET = 0x16;
	const CUSTOM_PAYLOAD_PACKET = 0x17;
	const CUSTOM_SOUND_PACKET = 0x18;
	const DISCONNECT_PACKET = 0x19;
	const ENTITY_EVENT_PACKET = 0x1a;
	const EXPLODE_PACKET = 0x1b;
	const FORGET_LEVEL_CHUNK_PACKET = 0x1c;
	const GAME_EVENT_PACKET = 0x1d;
	//TODO OPEN_HORSE_WINDOW_PACKET = 0x1e;
	const KEEP_ALIVE_PACKET = 0x1f;
	const LEVEL_CHUNK_PACKET = 0x20;
	const LEVEL_EVENT_PACKET = 0x21;
	const LEVEL_PARTICLES_PACKET = 0x22;
	const LIGHT_UPDATE_PACKET = 0x23;
	const LOGIN_PACKET = 0x24;
	const MAP_ITEM_DATA_PACKET = 0x25;
	//TODO TRADE_LIST_PACKET = 0x26;
	//TODO ENTITY_POSITION_PACKET = 0x27;
	//TODO ENTITY_POSITION_AND_ROTATION_PACKET = 0x28;
	const MOVE_ENTITY_ROTATION_PACKET = 0x29;
	const ENTITY_MOVEMENT_PACKET = 0x2a;
	//TODO VEHICLE_MOVE_PACKET = 0x2b;
	//TODO OPEN_BOOK_PACKET = 0x2c;
	const OPEN_SCREEN_PACKET = 0x2d;
	const OPEN_SIGN_EDITOR_PACKET = 0x2e;
	const PLACE_GHOST_RECIPE_PACKET = 0x2f;
	const PLAYER_ABILITIES_PACKET = 0x30;
	//TODO COMBAT_EVENT_PACKET = 0x31;
	const PLAYER_INFO_PACKET = 0x32;
	//TODO FACE_PLAYER_PACKET = 0x33;
	const PLAYER_POSITION_PACKET = 0x34;
	const RECIPE_PACKET = 0x35;
	const DESTROY_ENTITIES_PACKET = 0x36;
	const REMOVE_MOB_EFFECT_PACKET = 0x37;
	//TODO RESOURCE_PACK_SEND_PACKET = 0x38;
	const RESPAWN_PACKET = 0x39;
	const ROTATE_HEAD_PACKET = 0x3a;
	//TODO MULTI_BLOCK_CHANGE_PACKET = 0x3b:
	const SELECT_ADVANCEMENTS_TAB_PACKET = 0x3c;
	//TODO WORLD_BORDER_PACKET = 0x3d;
	//TODO CAMERA_PACKET = 0x3e;
	const SET_CARRIED_ITEM_PACKET = 0x3f;
	const SET_CHUNK_CACHE_CENTER_PACKET = 0x40;
	const SET_CHUNK_CACHE_RADIUS_PACKET = 0x41;
	const SET_DEFAULT_SPAWN_POSITION_PACKET = 0x42;
	const SET_DISPLAY_OBJECTIVE_PACKET = 0x43;
	const SET_ENTITY_DATA_PACKET = 0x44;
	//TODO ATTACH_ENTITY_PACKET = 0x45;
	const SET_ENTITY_MOTION_PACKET = 0x46;
	const SET_EQUIPMENT_PACKET = 0x47;
	const SET_EXPERIENCE_PACKET = 0x48;
	const SET_HEALTH_PACKET = 0x49;
	const SET_OBJECTIVE_PACKET = 0x4a;
	//TODO SET_PASSENGERS_PACKET = 0x4b;
	//TODO TEAMS_PACKET = 0x4c;
	const SET_SCORE_PACKET = 0x4d;
	const SET_TIME_PACKET = 0x4e;
	const TITLE_PACKET = 0x4f;
	//TODO ENTITY_SOUND_EFFECT_PACKET = 0x50;
	const SOUND_PACKET = 0x51;
	//TODO STOP_SOUND_PACKET = 0x52;
	//TODO PLAYER_LIST_HEADER_AND_FOOTER_PACKET = 0x53;
	//TODO NBT_QUERY_RESPONSE_PACKET = 0x54;
	const TAKE_ITEM_ENTITY_PACKET = 0x55;
	const TELEPORT_ENTITY_PACKET = 0x56;
	const UPDATE_ADVANCEMENTS_PACKET = 0x57;
	const UPDATE_ATTRIBUTES_PACKET = 0x58;
	const UPDATE_MOB_EFFECT_PACKET = 0x59;
	//TODO DECLARE_RECIPES_PACKET = 0x5a;
	//TODO TAGS_PACKET = 0x5b;

	//Status

	//Login
	const LOGIN_DISCONNECT_PACKET = 0x00;
	const HELLO_PACKET = 0x01;
	const GAME_PROFILE_PACKET = 0x02;

	/**
	 * @throws ErrorException
	 * @deprecated
	 */
	protected final function decode() : void{
		throw new ErrorException(get_class($this) . " is subclass of OutboundPacket: don't call decode() method");
	}
}
